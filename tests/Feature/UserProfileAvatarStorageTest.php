<?php

namespace Tests\Feature;

use App\Domain\Profile\Avatar\ProcessedAvatar;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileSystemBinding;
use App\Models\User;
use App\Services\Profile\UserProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileAvatarStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
    }

    public function test_it_stores_only_the_processed_avatar_through_the_private_system_managed_binding(): void
    {
        $user = User::factory()->create();
        $stored = app(UserProfileService::class)->storeAvatar($user, $this->avatar('first final avatar'));

        $this->assertDatabaseHas('file_filePossession', [
            'id' => $stored->possessionId,
            'idUser' => $user->id,
            'storageArea' => 'SYSTEM_MANAGED',
            'purpose' => UserProfileService::AVATAR_BINDING_KEY,
            'displayName' => 'profile-avatar.png',
            'state' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('file_systemBinding', [
            'idUser' => $user->id,
            'bindingKey' => UserProfileService::AVATAR_BINDING_KEY,
            'idFilePossession' => $stored->possessionId,
        ]);
    }

    public function test_it_replaces_the_avatar_binding_without_leaving_two_active_possessions(): void
    {
        $user = User::factory()->create();
        $first = app(UserProfileService::class)->storeAvatar($user, $this->avatar('first final avatar'));
        $second = app(UserProfileService::class)->storeAvatar($user, $this->avatar('second final avatar'));

        $binding = StoredFileSystemBinding::query()
            ->where('idUser', $user->id)
            ->where('bindingKey', UserProfileService::AVATAR_BINDING_KEY)
            ->firstOrFail();

        $this->assertSame($first->fileId, $second->fileId);
        $this->assertSame($first->possessionId, $second->replacedPossessionId);
        $this->assertSame($second->possessionId, $binding->idFilePossession);
        $this->assertSame('RELEASED', StoredFilePossession::query()->findOrFail($first->possessionId)->state);
        $this->assertSame(1, StoredFilePossession::query()
            ->where('idUser', $user->id)
            ->where('purpose', UserProfileService::AVATAR_BINDING_KEY)
            ->where('state', 'ACTIVE')
            ->count());
    }

    private function avatar(string $contents): ProcessedAvatar
    {
        return new ProcessedAvatar(
            contents: $contents,
            mimeType: 'image/png',
            extension: 'png',
            width: 400,
            height: 400,
        );
    }
}
