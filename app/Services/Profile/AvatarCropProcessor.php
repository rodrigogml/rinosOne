<?php

namespace App\Services\Profile;

use App\Domain\Profile\Avatar\AvatarCrop;
use App\Domain\Profile\Avatar\ProcessedAvatar;
use App\Domain\Profile\Avatar\ValidatedAvatarSource;
use App\Domain\Profile\Exception\AvatarValidationException;
use GdImage;
use RuntimeException;

class AvatarCropProcessor
{
    public function process(ValidatedAvatarSource $source, AvatarCrop $crop): ProcessedAvatar
    {
        [$sourceX, $sourceY, $sourceSide] = $this->sourceGeometry($source, $crop);
        $sourceImage = $this->decode($source);
        $outputImage = $this->newOutputImage();

        try {
            if (! imagecopyresampled(
                $outputImage,
                $sourceImage,
                0,
                0,
                $sourceX,
                $sourceY,
                imagesx($outputImage),
                imagesy($outputImage),
                $sourceSide,
                $sourceSide,
            )) {
                throw new RuntimeException('The avatar image could not be cropped.');
            }

            return $this->encode($outputImage, $source->mimeType);
        } finally {
            imagedestroy($outputImage);
            imagedestroy($sourceImage);
        }
    }

    /** @return array{int, int, int} */
    private function sourceGeometry(ValidatedAvatarSource $source, AvatarCrop $crop): array
    {
        if (! is_finite($crop->x)
            || ! is_finite($crop->y)
            || ! is_finite($crop->size)
            || $crop->x < 0
            || $crop->x > 1
            || $crop->y < 0
            || $crop->y > 1
            || $crop->size <= 0
            || $crop->size > 1) {
            throw new AvatarValidationException(AvatarValidationException::CROP_INVALID);
        }

        $sourceSide = (int) floor(min($source->width, $source->height) * $crop->size);
        $sourceX = (int) floor($source->width * $crop->x);
        $sourceY = (int) floor($source->height * $crop->y);

        if ($sourceSide < 1
            || $sourceX + $sourceSide > $source->width
            || $sourceY + $sourceSide > $source->height) {
            throw new AvatarValidationException(AvatarValidationException::CROP_INVALID);
        }

        return [$sourceX, $sourceY, $sourceSide];
    }

    private function decode(ValidatedAvatarSource $source): GdImage
    {
        $image = match ($source->mimeType) {
            'image/jpeg' => imagecreatefromjpeg($source->path),
            'image/png' => imagecreatefrompng($source->path),
            'image/webp' => imagecreatefromwebp($source->path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new AvatarValidationException(AvatarValidationException::FORMAT_UNSUPPORTED);
        }

        return $image;
    }

    private function newOutputImage(): GdImage
    {
        $dimension = config('profile.avatar.outputDimensionPixels');
        $image = imagecreatetruecolor($dimension, $dimension);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The avatar output image could not be initialized.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        return $image;
    }

    private function encode(GdImage $image, string $mimeType): ProcessedAvatar
    {
        ob_start();

        try {
            $encoded = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, null, 90),
                'image/png' => imagepng($image),
                'image/webp' => imagewebp($image, null, 90),
                default => false,
            };
            $contents = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        if (! $encoded || ! is_string($contents) || $contents === '') {
            throw new RuntimeException('The avatar image could not be encoded.');
        }

        return new ProcessedAvatar(
            contents: $contents,
            mimeType: $mimeType,
            extension: match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            },
            width: config('profile.avatar.outputDimensionPixels'),
            height: config('profile.avatar.outputDimensionPixels'),
        );
    }
}
