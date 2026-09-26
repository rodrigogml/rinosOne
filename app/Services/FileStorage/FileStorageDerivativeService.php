<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\ReserveFileVersionDerivativeRequest;
use App\Domain\FileStorage\Exception\FileStorageDerivativeException;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\StoredFileVersionDerivative;
use Illuminate\Support\Facades\DB;

class FileStorageDerivativeService
{
    /**
     * Reserves or updates an internal derivative relation without generating or exposing derivative bytes.
     */
    public function reserve(ReserveFileVersionDerivativeRequest $request): void
    {
        if ($request->sourceVersionId < 1
            || $request->derivativeContentId < 1
            || trim($request->derivativeKind) === ''
            || mb_strlen($request->derivativeKind) > 80
            || trim($request->derivativeKey) === ''
            || mb_strlen($request->derivativeKey) > 160
            || ! in_array($request->state, ['PENDING', 'ACTIVE', 'FAILED', 'RETIRED'], true)) {
            throw new FileStorageDerivativeException('The file version derivative request is invalid.');
        }

        DB::transaction(function () use ($request): void {
            $sourceVersion = StoredFileVersion::query()->lockForUpdate()->find($request->sourceVersionId);
            $content = StoredFileContent::query()->lockForUpdate()->find($request->derivativeContentId);

            if ($sourceVersion === null || $content === null || $sourceVersion->idFileContent === $content->id) {
                throw new FileStorageDerivativeException('The file version derivative relation is invalid.');
            }

            $derivative = StoredFileVersionDerivative::query()
                ->where('idSourceFileVersion', $sourceVersion->id)
                ->where('derivativeKind', $request->derivativeKind)
                ->where('derivativeKey', $request->derivativeKey)
                ->lockForUpdate()
                ->first();

            if ($derivative === null) {
                StoredFileVersionDerivative::query()->create([
                    'idSourceFileVersion' => $sourceVersion->id,
                    'idFileContent' => $content->id,
                    'derivativeKind' => $request->derivativeKind,
                    'derivativeKey' => $request->derivativeKey,
                    'state' => $request->state,
                ]);

                return;
            }

            $derivative->forceFill([
                'idFileContent' => $content->id,
                'state' => $request->state,
            ])->save();
        });
    }
}
