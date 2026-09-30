<?php

namespace App\Services\FileStorage\Drive;

use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceProjectionException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\User;
use Throwable;

class DriveWorkspaceDownloadService
{
    public function __construct(private readonly FileStorageV1 $fileStorage) {}

    /**
     * Revalidates a workspace file and opens its private stream immediately before HTTP transmission.
     *
     * @return array{stream: resource, displayName: string, detectedMimeType: string}
     *
     * @throws DriveWorkspaceProjectionException when the item is unavailable or no longer authorized.
     */
    public function open(User $principal, DriveWorkspaceTarget $target, int $possessionId): array
    {
        $possession = $this->possession($target, $possessionId);

        return $this->openPossession($principal, $possession);
    }

    /** Opens an active workspace file after direct-file or inherited authorization is revalidated. */
    public function openShared(User $principal, int $possessionId): array
    {
        $possession = StoredFilePossession::query()
            ->whereKey($possessionId)
            ->where('storageArea', 'WORKSPACE')
            ->where('state', 'ACTIVE')
            ->first();
        if ($possession === null) {
            throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
        }

        return $this->openPossession($principal, $possession);
    }

    /** @return array{stream: resource, displayName: string, detectedMimeType: string} */
    private function openPossession(User $principal, StoredFilePossession $possession): array
    {
        $ownerType = $possession->idUser === null ? FileStorageOwnerType::Tenant : FileStorageOwnerType::User;
        $ownerId = $possession->idUser ?? $possession->idTenant;
        if ($ownerId === null) {
            throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
        }

        try {
            $read = $this->fileStorage->authorizePrivateRead(new FilePrivateReadRequest(
                ownerType: $ownerType,
                ownerId: $ownerId,
                possessionId: $possession->id,
                principalUserId: $principal->id,
                allowTrashed: false,
            ));

            return [
                'stream' => $read->openStream(),
                'displayName' => $possession->displayName,
                'detectedMimeType' => $read->detectedMimeType,
            ];
        } catch (Throwable) {
            throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
        }
    }

    private function possession(DriveWorkspaceTarget $target, int $possessionId): StoredFilePossession
    {
        $query = StoredFilePossession::query()->whereKey($possessionId)->where('storageArea', 'WORKSPACE')->where('state', 'ACTIVE');
        if ($target->scope->value === 'TENANT') {
            $query->where('idTenant', $target->ownerId);
        } else {
            $query->whereNotNull('idUser')->whereNull('idTenant');
        }
        $possession = $query->first();
        if ($possession === null) {
            throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
        }

        return $possession;
    }
}
