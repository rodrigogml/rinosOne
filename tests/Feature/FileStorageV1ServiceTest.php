<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\StoredManagedVersion;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Domain\FileStorage\Exception\FileStorageVersionException;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileSystemBinding;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageV1ServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rinosone-file-storage-v1-'.str()->uuid();
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_creates_a_managed_version_binding_and_usage_for_a_user(): void
    {
        $user = User::factory()->create();

        $stored = $this->storeFor($user, 'first image');

        $this->assertDatabaseHas('file_fileVersion', ['id' => $stored->versionId, 'versionNumber' => 1]);
        $this->assertDatabaseHas('file_filePossession', [
            'id' => $stored->possessionId,
            'idUser' => $user->id,
            'storageArea' => 'SYSTEM_MANAGED',
            'purpose' => 'USER_PROFILE_AVATAR',
            'state' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('file_systemBinding', [
            'idUser' => $user->id,
            'bindingKey' => 'USER_PROFILE_AVATAR',
            'idFilePossession' => $stored->possessionId,
        ]);
        $this->assertSame(strlen('first image'), StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('systemManagedBytes'));
    }

    public function test_it_replaces_a_binding_with_a_new_version_and_releases_the_previous_possession(): void
    {
        $user = User::factory()->create();
        $first = $this->storeFor($user, 'first image');
        $second = $this->storeFor($user, 'second and larger image');

        $firstVersion = StoredFileVersion::query()->findOrFail($first->versionId);
        $secondVersion = StoredFileVersion::query()->findOrFail($second->versionId);
        $firstPossession = StoredFilePossession::query()->findOrFail($first->possessionId);
        $binding = StoredFileSystemBinding::query()->where('idUser', $user->id)->where('bindingKey', 'USER_PROFILE_AVATAR')->firstOrFail();
        $usage = StoredFileOwnerUsage::query()->where('idUser', $user->id)->firstOrFail();

        $this->assertSame($first->fileId, $second->fileId);
        $this->assertSame($firstVersion->id, $secondVersion->idParentFileVersion);
        $this->assertSame(2, $secondVersion->versionNumber);
        $this->assertSame('RELEASED', $firstPossession->state);
        $this->assertSame($second->possessionId, $binding->idFilePossession);
        $this->assertSame($first->possessionId, $second->replacedPossessionId);
        $this->assertSame(strlen('second and larger image'), $usage->systemManagedBytes);
        $this->assertSame($usage->systemManagedBytes, $usage->totalBytes);
    }

    public function test_it_rejects_a_binding_for_a_tenant_owner(): void
    {
        $this->expectException(FileStorageVersionException::class);
        $this->expectExceptionMessage('binding requires a user owner');

        app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::Tenant,
            ownerId: 1,
            sourcePath: $this->sourceFile('tenant.txt', 'tenant file'),
            purpose: 'TENANT_ASSET',
            displayName: 'tenant.txt',
            bindingKey: 'TENANT_ASSET',
        ));
    }

    public function test_it_creates_a_managed_possession_and_usage_for_a_tenant_without_a_user_binding(): void
    {
        $tenant = Tenant::query()->create([
            'displayName' => 'File tenant',
            'state' => 'ACTIVE',
        ]);

        $stored = app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::Tenant,
            ownerId: $tenant->id,
            sourcePath: $this->sourceFile('tenant.txt', 'tenant file'),
            purpose: 'TENANT_SYSTEM_ASSET',
            displayName: 'tenant.txt',
        ));

        $this->assertDatabaseHas('file_filePossession', [
            'id' => $stored->possessionId,
            'idTenant' => $tenant->id,
            'storageArea' => 'SYSTEM_MANAGED',
            'state' => 'ACTIVE',
        ]);
        $this->assertSame(strlen('tenant file'), StoredFileOwnerUsage::query()->where('idTenant', $tenant->id)->value('systemManagedBytes'));
        $this->assertDatabaseMissing('file_systemBinding', ['idFilePossession' => $stored->possessionId]);
    }

    public function test_it_rejects_a_parent_version_from_another_active_binding_lineage(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $first = $this->storeFor($firstUser, 'first lineage');
        $second = $this->storeFor($secondUser, 'second lineage');

        $this->expectException(FileStorageVersionException::class);
        $this->expectExceptionMessage('does not belong to the active binding lineage');

        app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $firstUser->id,
            sourcePath: $this->sourceFile('invalid-parent.txt', 'invalid parent'),
            purpose: 'USER_PROFILE_AVATAR',
            displayName: 'invalid-parent.txt',
            bindingKey: 'USER_PROFILE_AVATAR',
            parentVersionId: $second->versionId,
        ));
    }

    private function storeFor(User $user, string $contents): StoredManagedVersion
    {
        return app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $user->id,
            sourcePath: $this->sourceFile(str()->uuid().'.txt', $contents),
            purpose: 'USER_PROFILE_AVATAR',
            displayName: 'profile-avatar.txt',
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
    }

    private function sourceFile(string $name, string $contents): string
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
