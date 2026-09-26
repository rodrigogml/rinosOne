<?php

namespace Tests\Feature;

use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class FileStoragePersistenceConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_file_storage_catalog_contains_the_required_private_tables(): void
    {
        $this->assertTrue(Schema::hasTable('file_file'));
        $this->assertTrue(Schema::hasTable('file_fileVersion'));
        $this->assertTrue(Schema::hasTable('file_fileContent'));
        $this->assertTrue(Schema::hasTable('file_storageBackend'));
        $this->assertTrue(Schema::hasTable('file_storageObject'));
        $this->assertTrue(Schema::hasTable('file_filePossession'));
        $this->assertTrue(Schema::hasTable('file_systemBinding'));
        $this->assertTrue(Schema::hasTable('file_ownerUsage'));
        $this->assertTrue(Schema::hasTable('file_versionMetadata'));
        $this->assertTrue(Schema::hasTable('file_versionDerivative'));
    }

    public function test_content_hashes_are_unique_and_versions_require_valid_file_and_content_references(): void
    {
        $content = $this->createContent();

        $this->expectException(QueryException::class);

        StoredFileContent::query()->create([
            'logicalSha256' => $content->logicalSha256,
            'logicalSizeBytes' => 10,
            'detectedMimeType' => 'text/plain',
        ]);
    }

    public function test_a_version_cannot_reference_a_missing_file_or_content(): void
    {
        $this->expectException(QueryException::class);

        DB::table('file_fileVersion')->insert([
            'idFile' => 999999,
            'idFileContent' => 999999,
            'versionNumber' => 1,
        ]);
    }

    public function test_a_possession_requires_exactly_one_owner(): void
    {
        [$file, $version] = $this->createFileWithVersion();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('exactly one owner');

        StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'document.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 10,
        ]);
    }

    public function test_a_possession_with_a_user_owner_persists(): void
    {
        [$file, $version] = $this->createFileWithVersion();
        $user = User::factory()->create();

        $possession = StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $user->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'document.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 10,
        ]);

        $this->assertSame($user->id, $possession->idUser);
        $this->assertNull($possession->idTenant);
    }

    public function test_owner_usage_requires_exactly_one_owner(): void
    {
        $user = User::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('exactly one owner');

        StoredFileOwnerUsage::query()->create([
            'workspaceBytes' => 0,
            'systemManagedBytes' => 0,
            'trashBytes' => 0,
            'totalBytes' => 0,
        ]);
    }

    public function test_a_system_binding_key_is_unique_for_each_user(): void
    {
        $user = User::factory()->create();

        DB::table('file_systemBinding')->insert([
            'idUser' => $user->id,
            'bindingKey' => 'USER_PROFILE_AVATAR',
        ]);

        $this->expectException(QueryException::class);

        DB::table('file_systemBinding')->insert([
            'idUser' => $user->id,
            'bindingKey' => 'USER_PROFILE_AVATAR',
        ]);
    }

    /**
     * @return array{0: StoredFile, 1: StoredFileVersion}
     */
    private function createFileWithVersion(): array
    {
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $content = $this->createContent();

        return [
            $file,
            StoredFileVersion::query()->create([
                'idFile' => $file->id,
                'idFileContent' => $content->id,
                'versionNumber' => 1,
            ]),
        ];
    }

    private function createContent(): StoredFileContent
    {
        return StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', (string) str()->uuid()),
            'logicalSizeBytes' => 10,
            'detectedMimeType' => 'text/plain',
            'declaredExtension' => 'txt',
        ]);
    }
}
