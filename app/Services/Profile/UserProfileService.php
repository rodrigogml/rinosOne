<?php

namespace App\Services\Profile;

use App\Contracts\FileStorage\V1\AuthorizedPrivateFileRead;
use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\ManagedBindingStatusRequest;
use App\Contracts\FileStorage\V1\ReleaseManagedBindingRequest;
use App\Contracts\FileStorage\V1\StoredManagedVersion;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Domain\Profile\Avatar\AvatarCrop;
use App\Domain\Profile\Avatar\ProcessedAvatar;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class UserProfileService
{
    public const AVATAR_BINDING_KEY = 'USER_PROFILE_AVATAR';

    public function __construct(
        private readonly FileStorageV1 $fileStorage,
        private readonly AvatarSourceValidator $sourceValidator,
        private readonly AvatarCropProcessor $cropProcessor,
    ) {}

    /** @return array{user: array{id: int, displayName: string}, avatar: array{available: bool, url: null, updatedAt: null}} */
    public function describe(User $user): array
    {
        $avatar = $this->fileStorage->managedBindingStatus(new ManagedBindingStatusRequest(
            ownerId: $user->id,
            bindingKey: self::AVATAR_BINDING_KEY,
            purpose: self::AVATAR_BINDING_KEY,
        ));

        return [
            'user' => [
                'id' => $user->id,
                'displayName' => $user->displayName,
            ],
            'avatar' => [
                'available' => $avatar->available,
                'url' => $avatar->available ? '/api/v1/profile/avatar' : null,
                'updatedAt' => $avatar->updatedAt?->toISOString(),
            ],
        ];
    }

    /** @return array{user: array{id: int, displayName: string}, avatar: array{available: bool, url: null, updatedAt: null}} */
    public function updateDisplayName(User $user, string $displayName): array
    {
        $user->forceFill(['displayName' => trim($displayName)])->save();

        return $this->describe($user->refresh());
    }

    public function storeAvatar(User $user, ProcessedAvatar $avatar): StoredManagedVersion
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'rinos-profile-avatar-');

        if ($temporaryPath === false || file_put_contents($temporaryPath, $avatar->contents) !== strlen($avatar->contents)) {
            if (is_string($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw new RuntimeException('The processed avatar could not be prepared for storage.');
        }

        try {
            return $this->fileStorage->storeManagedVersion(new StoreManagedVersionRequest(
                ownerType: FileStorageOwnerType::User,
                ownerId: $user->id,
                sourcePath: $temporaryPath,
                purpose: self::AVATAR_BINDING_KEY,
                displayName: 'profile-avatar.'.$avatar->extension,
                bindingKey: self::AVATAR_BINDING_KEY,
            ));
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function replaceAvatar(User $user, UploadedFile $image, AvatarCrop $crop): array
    {
        $source = $this->sourceValidator->validate($image);
        $avatar = $this->cropProcessor->process($source, $crop);
        $this->storeAvatar($user, $avatar);

        return $this->describe($user);
    }

    public function readAvatar(User $user): AuthorizedPrivateFileRead
    {
        return $this->fileStorage->authorizePrivateRead(new FilePrivateReadRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $user->id,
            bindingKey: self::AVATAR_BINDING_KEY,
            principalUserId: $user->id,
        ));
    }

    public function removeAvatar(User $user): void
    {
        $this->fileStorage->releaseManagedBinding(new ReleaseManagedBindingRequest(
            ownerId: $user->id,
            bindingKey: self::AVATAR_BINDING_KEY,
            purpose: self::AVATAR_BINDING_KEY,
        ));
    }
}
