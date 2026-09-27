<?php

namespace App\Services\Profile;

use App\Domain\Profile\Avatar\ValidatedAvatarSource;
use App\Domain\Profile\Exception\AvatarValidationException;
use Illuminate\Http\UploadedFile;

class AvatarSourceValidator
{
    public function __construct(private readonly AvatarProcessingCapability $capability) {}

    public function validate(UploadedFile $image): ValidatedAvatarSource
    {
        $this->capability->assertAvailable();

        $sourcePath = $image->getRealPath() ?: $image->getPathname();
        $sizeBytes = $image->getSize();

        if (! $image->isValid() || $sourcePath === '' || $sizeBytes === false || $sizeBytes < 1) {
            throw new AvatarValidationException(AvatarValidationException::FORMAT_UNSUPPORTED);
        }

        if ($sizeBytes > config('profile.avatar.maximumSizeBytes')) {
            throw new AvatarValidationException(AvatarValidationException::TOO_LARGE);
        }

        $imageInfo = @getimagesize($sourcePath);
        $detectedMimeType = is_array($imageInfo) ? (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath) : false;

        if (! is_array($imageInfo) || ! is_string($detectedMimeType) || ! in_array($detectedMimeType, config('profile.avatar.allowedMimeTypes'), true)) {
            throw new AvatarValidationException(AvatarValidationException::FORMAT_UNSUPPORTED);
        }

        $minimumDimension = config('profile.avatar.minimumDimensionPixels');
        if ($imageInfo[0] < $minimumDimension || $imageInfo[1] < $minimumDimension) {
            throw new AvatarValidationException(AvatarValidationException::DIMENSIONS_TOO_SMALL);
        }

        return new ValidatedAvatarSource($sourcePath, $detectedMimeType, $imageInfo[0], $imageInfo[1]);
    }
}
