<?php

namespace Tests\Unit;

use App\Domain\Profile\Avatar\AvatarCrop;
use App\Domain\Profile\Avatar\ValidatedAvatarSource;
use App\Domain\Profile\Exception\AvatarValidationException;
use App\Services\Profile\AvatarCropProcessor;
use Tests\TestCase;

class AvatarCropProcessorTest extends TestCase
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

    public function test_it_generates_only_a_square_final_image_without_changing_the_source(): void
    {
        $source = $this->sourceImage();
        $originalHash = hash_file('sha256', $source->path);

        $result = app(AvatarCropProcessor::class)->process($source, new AvatarCrop(0.5, 0, 0.5));

        $imageInfo = getimagesizefromstring($result->contents);
        $resultImage = imagecreatefromstring($result->contents);
        $color = imagecolorsforindex($resultImage, imagecolorat($resultImage, 200, 200));
        imagedestroy($resultImage);

        $this->assertSame(400, $result->width);
        $this->assertSame(400, $result->height);
        $this->assertSame(400, $imageInfo[0]);
        $this->assertSame(400, $imageInfo[1]);
        $this->assertSame('image/png', $result->mimeType);
        $this->assertSame('png', $result->extension);
        $this->assertGreaterThan($color['red'], $color['blue']);
        $this->assertSame($originalHash, hash_file('sha256', $source->path));
        $this->assertArrayNotHasKey('path', get_object_vars($result));
    }

    public function test_it_rejects_a_crop_outside_the_source_boundaries(): void
    {
        $this->assertInvalidCrop(new AvatarCrop(0.75, 0, 1));
        $this->assertInvalidCrop(new AvatarCrop(-0.1, 0, 0.5));
        $this->assertInvalidCrop(new AvatarCrop(0, 0, 0));
    }

    public function test_it_generates_a_400_pixel_output_for_each_allowed_source_format(): void
    {
        foreach (['jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $format => $mimeType) {
            $result = app(AvatarCropProcessor::class)->process($this->sourceImage($format), new AvatarCrop(0, 0, 1));
            $imageInfo = getimagesizefromstring($result->contents);

            $this->assertSame($mimeType, $result->mimeType);
            $this->assertSame(400, $imageInfo[0]);
            $this->assertSame(400, $imageInfo[1]);
        }
    }

    private function assertInvalidCrop(AvatarCrop $crop): void
    {
        try {
            app(AvatarCropProcessor::class)->process($this->sourceImage(), $crop);
            $this->fail('The avatar crop should have been rejected.');
        } catch (AvatarValidationException $exception) {
            $this->assertSame(AvatarValidationException::CROP_INVALID, $exception->errorCode);
        }
    }

    private function sourceImage(string $format = 'png'): ValidatedAvatarSource
    {
        $path = tempnam(sys_get_temp_dir(), 'rinos-avatar-crop-');
        $image = imagecreatetruecolor(800, 400);
        $red = imagecolorallocate($image, 220, 20, 60);
        $blue = imagecolorallocate($image, 30, 90, 220);
        imagefilledrectangle($image, 0, 0, 399, 399, $red);
        imagefilledrectangle($image, 400, 0, 799, 399, $blue);
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };
        imagedestroy($image);
        $this->temporaryFiles[] = $path;

        return new ValidatedAvatarSource($path, 'image/'.$format, 800, 400);
    }
}
