<?php

namespace Tests\Feature;

use App\Domain\Profile\Avatar\ProcessedAvatar;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileSystemBinding;
use App\Models\User;
use App\Services\Profile\UserProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarPrivateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
    }

    public function test_an_authenticated_user_can_read_only_their_private_avatar(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        app(UserProfileService::class)->storeAvatar($owner, $this->avatar());

        $response = $this->actingAs($owner)->get('/api/v1/profile/avatar');

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $response->assertHeader('x-content-type-options', 'nosniff');
        $this->assertSame($this->avatar()->contents, $response->streamedContent());
        $this->assertStringNotContainsString('objects/', $response->streamedContent());

        $this->actingAs($otherUser)->get('/api/v1/profile/avatar')->assertNotFound();
    }

    public function test_an_authenticated_user_can_remove_their_avatar_without_releasing_another_users_avatar(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownerStored = app(UserProfileService::class)->storeAvatar($owner, $this->avatar());
        $otherStored = app(UserProfileService::class)->storeAvatar($otherUser, $this->avatar());

        $this->actingAs($owner)->delete('/api/v1/profile/avatar')->assertNoContent();

        $this->assertDatabaseHas('file_systemBinding', [
            'idUser' => $owner->id,
            'bindingKey' => UserProfileService::AVATAR_BINDING_KEY,
            'idFilePossession' => null,
        ]);
        $this->assertSame('RELEASED', StoredFilePossession::query()->findOrFail($ownerStored->possessionId)->state);
        $this->assertSame(0, StoredFileOwnerUsage::query()->where('idUser', $owner->id)->value('systemManagedBytes'));
        $this->assertSame($otherStored->possessionId, StoredFileSystemBinding::query()
            ->where('idUser', $otherUser->id)
            ->where('bindingKey', UserProfileService::AVATAR_BINDING_KEY)
            ->value('idFilePossession'));
        $this->actingAs($owner)->get('/api/v1/profile/avatar')->assertNotFound();
        $this->actingAs($owner)->delete('/api/v1/profile/avatar')->assertNoContent();
        $this->actingAs($owner)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('user.displayName', $owner->displayName)
            ->assertJsonPath('avatar.available', false)
            ->assertJsonPath('avatar.url', null)
            ->assertJsonPath('avatar.updatedAt', null);
    }

    public function test_avatar_routes_require_an_authenticated_session(): void
    {
        $this->getJson('/api/v1/profile/avatar')->assertUnauthorized();
        $this->deleteJson('/api/v1/profile/avatar')->assertUnauthorized();
    }

    private function avatar(): ProcessedAvatar
    {
        return new ProcessedAvatar(
            contents: base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M/wHwAF/gL+3MXGJwAAAABJRU5ErkJggg==', true),
            mimeType: 'image/png',
            extension: 'png',
            width: 400,
            height: 400,
        );
    }
}
