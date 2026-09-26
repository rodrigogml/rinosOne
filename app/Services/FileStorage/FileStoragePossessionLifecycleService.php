<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\FilePossessionLifecycleResult;
use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\FileStorage\Exception\FileStorageVersionException;
use App\Domain\FileStorage\Retention\FileStorageRetentionPolicy;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageObject;
use Illuminate\Support\Facades\DB;

class FileStoragePossessionLifecycleService
{
    public function __construct(private readonly FileStorageRetentionPolicy $retentionPolicy) {}

    /**
     * Moves an owner-authorized workspace possession to trash and preserves its total logical quota.
     */
    public function trash(FilePossessionOperationRequest $request): FilePossessionLifecycleResult
    {
        return DB::transaction(function () use ($request): FilePossessionLifecycleResult {
            $possession = $this->resolveWorkspacePossession($request, 'ACTIVE');
            $trashedAt = now()->toImmutable();
            $purgeAfter = $this->retentionPolicy->purgeAfter($trashedAt);

            $possession->forceFill([
                'state' => 'TRASHED',
                'trashedAt' => $trashedAt,
                'purgeAfter' => $purgeAfter,
            ])->save();
            $this->adjustUsage($possession, -$possession->logicalSizeBytes, $possession->logicalSizeBytes, 0);

            return $this->result($possession);
        });
    }

    /**
     * Restores an owner-authorized workspace possession that has not reached its purge deadline.
     */
    public function restore(FilePossessionOperationRequest $request): FilePossessionLifecycleResult
    {
        return DB::transaction(function () use ($request): FilePossessionLifecycleResult {
            $possession = $this->resolveWorkspacePossession($request, 'TRASHED');

            if ($possession->purgeAfter === null || $possession->purgeAfter->isPast()) {
                throw new FileStorageVersionException('The file possession can no longer be restored from the trash.');
            }

            $possession->forceFill([
                'state' => 'ACTIVE',
                'trashedAt' => null,
                'purgeAfter' => null,
            ])->save();
            $this->adjustUsage($possession, $possession->logicalSizeBytes, -$possession->logicalSizeBytes, 0);

            return $this->result($possession);
        });
    }

    /**
     * Releases an owner-authorized workspace possession irreversibly and starts technical byte retention.
     */
    public function release(FilePossessionOperationRequest $request): FilePossessionLifecycleResult
    {
        return DB::transaction(function () use ($request): FilePossessionLifecycleResult {
            $possession = $this->resolveWorkspacePossession($request, ['ACTIVE', 'TRASHED']);

            return $this->releasePossession($possession);
        });
    }

    /**
     * Releases a superseded system possession without allowing it through generic workspace operations.
     */
    public function releaseSupersededSystemPossession(StoredFilePossession $possession): void
    {
        if ($possession->storageArea !== 'SYSTEM_MANAGED' || $possession->state !== 'ACTIVE') {
            return;
        }

        $possession->forceFill([
            'state' => 'RELEASED',
            'releasedAt' => now(),
        ])->save();
        $this->retainCurrentVersionContent($possession);
    }

    /**
     * Purges expired trashed workspace possessions in small independent transactions.
     */
    public function purgeExpiredTrash(int $batchSize = 100): int
    {
        $ids = StoredFilePossession::query()
            ->where('storageArea', 'WORKSPACE')
            ->where('state', 'TRASHED')
            ->whereNotNull('purgeAfter')
            ->where('purgeAfter', '<=', now())
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');
        $purged = 0;

        foreach ($ids as $id) {
            $released = DB::transaction(function () use ($id): bool {
                $possession = StoredFilePossession::query()->lockForUpdate()->find($id);

                if ($possession === null
                    || $possession->storageArea !== 'WORKSPACE'
                    || $possession->state !== 'TRASHED'
                    || $possession->purgeAfter === null
                    || $possession->purgeAfter->isFuture()) {
                    return false;
                }

                $this->releasePossession($possession);

                return true;
            });
            $purged += $released ? 1 : 0;
        }

        return $purged;
    }

    private function resolveWorkspacePossession(FilePossessionOperationRequest $request, string|array $states): StoredFilePossession
    {
        if ($request->ownerId < 1 || $request->possessionId < 1) {
            throw new FileStorageVersionException('The file possession operation request is invalid.');
        }

        $ownerColumn = $request->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
        $possession = StoredFilePossession::query()
            ->whereKey($request->possessionId)
            ->where($ownerColumn, $request->ownerId)
            ->lockForUpdate()
            ->first();

        if ($possession === null) {
            throw new FileStorageVersionException('The file possession does not exist or is not available to this owner.');
        }

        if ($possession->storageArea !== 'WORKSPACE') {
            throw new FileStorageVersionException('Generic file possession operations are not allowed for system-managed files.');
        }

        if (! in_array($possession->state, (array) $states, true)) {
            throw new FileStorageVersionException('The file possession is not in a state compatible with this operation.');
        }

        return $possession;
    }

    private function releasePossession(StoredFilePossession $possession): FilePossessionLifecycleResult
    {
        $wasTrashed = $possession->state === 'TRASHED';
        $possession->forceFill([
            'state' => 'RELEASED',
            'releasedAt' => now(),
        ])->save();
        $this->adjustUsage(
            $possession,
            $wasTrashed ? 0 : -$possession->logicalSizeBytes,
            $wasTrashed ? -$possession->logicalSizeBytes : 0,
            -$possession->logicalSizeBytes,
        );
        $this->retainCurrentVersionContent($possession);

        return $this->result($possession);
    }

    private function retainCurrentVersionContent(StoredFilePossession $possession): void
    {
        $version = $possession->currentVersion()->lockForUpdate()->first();

        if ($version === null) {
            return;
        }

        $retentionUntil = $this->retentionPolicy->retentionUntil();
        StoredFileStorageObject::query()
            ->where('idFileContent', $version->idFileContent)
            ->where(function ($query) use ($retentionUntil): void {
                $query->whereNull('retentionUntil')->orWhere('retentionUntil', '<', $retentionUntil);
            })
            ->update(['retentionUntil' => $retentionUntil]);
    }

    private function adjustUsage(StoredFilePossession $possession, int $workspaceDelta, int $trashDelta, int $totalDelta): void
    {
        $ownerColumn = $possession->idUser === null ? 'idTenant' : 'idUser';
        $ownerId = $possession->{$ownerColumn};
        $usage = StoredFileOwnerUsage::query()->where($ownerColumn, $ownerId)->lockForUpdate()->firstOrFail();

        $usage->forceFill([
            'workspaceBytes' => max(0, (int) $usage->workspaceBytes + $workspaceDelta),
            'trashBytes' => max(0, (int) $usage->trashBytes + $trashDelta),
            'totalBytes' => max(0, (int) $usage->totalBytes + $totalDelta),
        ])->save();
    }

    private function result(StoredFilePossession $possession): FilePossessionLifecycleResult
    {
        return new FilePossessionLifecycleResult(
            possessionId: $possession->id,
            state: $possession->state,
            purgeAfter: $possession->purgeAfter?->toImmutable(),
            releasedAt: $possession->releasedAt?->toImmutable(),
        );
    }
}
