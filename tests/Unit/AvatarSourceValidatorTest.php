<?php

namespace Tests\Unit;

use App\Domain\Profile\Exception\AvatarValidationException;
use App\Services\Profile\AvatarSourceValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvatarSourceValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }

        parent::tearDown();
    }

    public function test_it_accepts_jpeg_png_and_webp_sources_at_or_above_the_minimum_dimensions(): void
    {
        $validator = app(AvatarSourceValidator::class);

        foreach (['jpeg', 'png', 'webp'] as $format) {
            $validated = $validator->validate($this->image($format, 400, 480));

            $this->assertSame('image/'.$format, $validated->mimeType);
            $this->assertSame(400, $validated->width);
            $this->assertSame(480, $validated->height);
        }
    }

    public function test_it_rejects_a_non_image_even_when_the_client_declares_an_image_type(): void
    {
        $file = $this->temporaryFile('not an image');
        $upload = new UploadedFile($file, 'avatar.png', 'image/png', null, true);

        $this->assertValidationError(AvatarValidationException::FORMAT_UNSUPPORTED, fn () => app(AvatarSourceValidator::class)->validate($upload));
    }

    public function test_it_rejects_a_source_larger_than_the_configured_limit_before_processing(): void
    {
        config()->set('profile.avatar.maximumSizeBytes', 1);

        $this->assertValidationError(AvatarValidationException::TOO_LARGE, fn () => app(AvatarSourceValidator::class)->validate($this->image('png', 400, 400)));
    }

    public function test_it_rejects_a_source_with_either_dimension_below_the_configured_minimum(): void
    {
        $this->assertValidationError(AvatarValidationException::DIMENSIONS_TOO_SMALL, fn () => app(AvatarSourceValidator::class)->validate($this->image('jpeg', 399, 400)));
    }

    private function image(string $format, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        $path = $this->temporaryFile();

        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };
        imagedestroy($image);

        return new UploadedFile($path, 'avatar.'.$format, 'image/'.$format, null, true);
    }

    private function temporaryFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rinos-avatar-');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /** @param callable(): void $operation */
    private function assertValidationError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            $this->fail('The avatar source should have been rejected.');
        } catch (AvatarValidationException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
        }
    }
}
