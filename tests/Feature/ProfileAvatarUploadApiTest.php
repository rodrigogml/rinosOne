<?php

namespace Tests\Feature;

use App\Models\FileStorage\StoredFilePossession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarUploadApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }

        parent::tearDown();
    }

    public function test_an_authenticated_user_can_store_a_processed_avatar_and_receive_the_updated_profile(): void
    {
        $user = User::factory()->create(['displayName' => 'Ana Souza']);

        $response = $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/v1/profile/avatar', [
            'image' => $this->image('png', 800, 400),
            'cropX' => 0.5,
            'cropY' => 0,
            'cropSize' => 0.5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('avatar.available', true)
            ->assertJsonPath('avatar.url', '/api/v1/profile/avatar')
            ->assertJsonPath('avatar.updatedAt', fn ($value) => is_string($value) && $value !== '');
        $this->assertSame(1, StoredFilePossession::query()->where('idUser', $user->id)->where('state', 'ACTIVE')->count());

        $avatar = $this->actingAs($user)->get('/api/v1/profile/avatar');
        $avatar->assertOk()->assertHeader('content-type', 'image/png');
        $imageInfo = getimagesizefromstring($avatar->streamedContent());
        $this->assertSame(400, $imageInfo[0]);
        $this->assertSame(400, $imageInfo[1]);
    }

    public function test_an_invalid_upload_or_crop_is_rejected_without_replacing_the_active_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/v1/profile/avatar', [
            'image' => $this->image('jpeg', 800, 800),
            'cropX' => 0,
            'cropY' => 0,
            'cropSize' => 1,
        ])->assertCreated();
        $activePossessionId = StoredFilePossession::query()->where('idUser', $user->id)->where('state', 'ACTIVE')->value('id');

        $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/v1/profile/avatar', [
            'image' => $this->image('png', 800, 400),
            'cropX' => 0.75,
            'cropY' => 0,
            'cropSize' => 1,
        ])->assertUnprocessable()->assertJsonPath('code', 'AVATAR_CROP_INVALID');

        $this->assertSame($activePossessionId, StoredFilePossession::query()->where('idUser', $user->id)->where('state', 'ACTIVE')->value('id'));
    }

    public function test_an_unsupported_upload_returns_the_stable_avatar_validation_code(): void
    {
        $user = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'rinos-profile-invalid-');
        file_put_contents($path, 'not an image');
        $this->temporaryFiles[] = $path;

        $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/v1/profile/avatar', [
            'image' => new UploadedFile($path, 'avatar.png', 'image/png', null, true),
            'cropX' => 0,
            'cropY' => 0,
            'cropSize' => 1,
        ])->assertUnprocessable()->assertJsonPath('code', 'AVATAR_FORMAT_UNSUPPORTED');
    }

    public function test_the_avatar_upload_route_requires_an_authenticated_session(): void
    {
        $this->withHeader('Accept', 'application/json')->post('/api/v1/profile/avatar', [
            'image' => $this->image('png', 800, 800),
            'cropX' => 0,
            'cropY' => 0,
            'cropSize' => 1,
        ])->assertUnauthorized();
    }

    private function image(string $format, int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rinos-profile-upload-');
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 130, 20, 80);
        imagefill($image, 0, 0, $color);
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };
        imagedestroy($image);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'avatar.'.$format, 'image/'.$format, null, true);
    }
}
