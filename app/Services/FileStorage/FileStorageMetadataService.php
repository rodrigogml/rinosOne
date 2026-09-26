<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\StoreFileVersionMetadataRequest;
use App\Domain\FileStorage\Exception\FileStorageMetadataException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersionMetadata;
use Illuminate\Support\Facades\DB;

class FileStorageMetadataService
{
    /**
     * Persists source-attributed metadata for the current version of an owner-authorized possession.
     */
    public function store(StoreFileVersionMetadataRequest $request): void
    {
        if ($request->ownerId < 1
            || $request->possessionId < 1
            || trim($request->metadataKey) === ''
            || mb_strlen($request->metadataKey) > 120
            || $request->metadataValue === []
            || ! in_array($request->source, ['EXTRACTED', 'DECLARED'], true)) {
            throw new FileStorageMetadataException('The file version metadata request is invalid.');
        }

        DB::transaction(function () use ($request): void {
            $ownerColumn = $request->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
            $possession = StoredFilePossession::query()
                ->whereKey($request->possessionId)
                ->where($ownerColumn, $request->ownerId)
                ->whereIn('state', ['ACTIVE', 'TRASHED'])
                ->lockForUpdate()
                ->first();

            if ($possession === null) {
                throw new FileStorageMetadataException('The file version is not available to this owner.');
            }

            StoredFileVersionMetadata::query()->create([
                'idFileVersion' => $possession->idCurrentFileVersion,
                'metadataKey' => $request->metadataKey,
                'metadataValue' => $request->metadataValue,
                'source' => $request->source,
            ]);
        });
    }
}
