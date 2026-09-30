<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Jobs\FileStorage\GenerateWorkspaceExport;
use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use App\Services\FileStorage\FileStoragePrivateReadService;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\FileStorage\WorkspaceExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class DriveWorkspaceExportService
{
    public function __construct(
        private readonly DriveWorkspaceProjectionService $projections,
        private readonly FileStoragePrivateReadService $privateReads,
        private readonly FileStorageBackendResolver $backends,
    ) {}

    /**
     * Registers only a complete, active selection. Authorization is revalidated again by the generation job.
     *
     * @param  list<array{type: string, id: int}>  $items
     */
    public function request(User $principal, DriveWorkspaceTarget $target, array $items): WorkspaceExport
    {
        if (count($items) < 2 || count($items) > (int) config('file-storage.workspaceExport.maximumItems', 100)) {
            throw new DriveWorkspaceCommandException('DRIVE_EXPORT_LIMIT_EXCEEDED');
        }

        $export = DB::transaction(function () use ($principal, $target, $items): WorkspaceExport {
            foreach ($items as $item) {
                $this->assertReadable($principal, $target, $item);
            }

            return WorkspaceExport::query()->create([
                'publicId' => (string) Str::ulid(),
                'idRequestingUser' => $principal->id,
                'idTenant' => $target->tenantId,
                'workspaceScope' => $target->scope->value,
                'selectionManifest' => $items,
                'state' => 'PENDING',
                'displayName' => 'Rinos Drive export.zip',
                'expiresAt' => now()->addMinutes((int) config('file-storage.workspaceExport.lifetimeMinutes', 60)),
            ]);
        });

        GenerateWorkspaceExport::dispatch($export->publicId)->afterCommit();

        return $export;
    }

    /** Returns only an export requested by this user in this exact workspace context. */
    public function status(User $principal, DriveWorkspaceTarget $target, string $publicId): WorkspaceExport
    {
        $export = WorkspaceExport::query()
            ->where('publicId', $publicId)
            ->where('idRequestingUser', $principal->id)
            ->where('workspaceScope', $target->scope->value)
            ->where('idTenant', $target->tenantId)
            ->first();

        if ($export === null || $export->expiresAt->isPast()) throw new DriveWorkspaceCommandException('DRIVE_EXPORT_NOT_FOUND');

        return $export;
    }

    /** Cancels only a pending export owned by the current principal in the current workspace. */
    public function cancel(User $principal, DriveWorkspaceTarget $target, string $publicId): WorkspaceExport
    {
        return DB::transaction(function () use ($principal, $target, $publicId): WorkspaceExport {
            $export = $this->status($principal, $target, $publicId);
            if ($export->state === 'PENDING') $export->update(['state' => 'CANCELLED']);

            return $export->refresh();
        });
    }

    /** @return array{stream: resource, displayName: string} */
    public function openDownload(User $principal, DriveWorkspaceTarget $target, string $publicId): array
    {
        $export = $this->status($principal, $target, $publicId);
        if ($export->state !== 'READY' || $export->storageKey === null) throw new DriveWorkspaceCommandException('DRIVE_EXPORT_NOT_READY');
        try {
            $stream = $this->backends->disk()->readStream($export->storageKey);
        } catch (Throwable) {
            throw new DriveWorkspaceCommandException('DRIVE_EXPORT_NOT_READY');
        }
        if (! is_resource($stream)) throw new DriveWorkspaceCommandException('DRIVE_EXPORT_NOT_READY');

        return ['stream' => $stream, 'displayName' => $export->displayName];
    }

    /** Purges expired temporary archives and their registry rows in bounded independent transactions. */
    public function purgeExpired(int $batchSize = 100): void
    {
        WorkspaceExport::query()->where('expiresAt', '<=', now())->orderBy('id')->limit($batchSize)->get()->each(function (WorkspaceExport $export): void {
            DB::transaction(function () use ($export): void {
                $locked = WorkspaceExport::query()->whereKey($export->id)->lockForUpdate()->first();
                if ($locked === null || $locked->expiresAt->isFuture()) return;
                if ($locked->storageKey !== null) $this->backends->disk()->delete($locked->storageKey);
                $locked->delete();
            });
        });
        $this->purgeOrphanedArchives();
    }

    /** Removes only aged private ZIPs that do not have a surviving export registry row. */
    private function purgeOrphanedArchives(): void
    {
        $disk = $this->backends->disk();
        $known = WorkspaceExport::query()->whereNotNull('storageKey')->pluck('storageKey')->flip();
        $threshold = now()->subMinutes((int) config('file-storage.workspaceExport.lifetimeMinutes', 60))->getTimestamp();
        foreach ($disk->allFiles('workspace-exports') as $storageKey) {
            if ($known->has($storageKey) || ! str_ends_with($storageKey, '.zip')) continue;
            try {
                if ($disk->lastModified($storageKey) <= $threshold) $disk->delete($storageKey);
            } catch (Throwable) {
                // A transient backend failure must not stop cleanup of other export rows.
            }
        }
    }

    /** Builds an opaque private archive for one pending export. Safe to invoke repeatedly. */
    public function generate(string $publicId): void
    {
        $export = DB::transaction(function () use ($publicId): ?WorkspaceExport {
            $candidate = WorkspaceExport::query()->where('publicId', $publicId)->lockForUpdate()->first();
            if ($candidate === null || $candidate->expiresAt->isPast() || $candidate->state !== 'PENDING') return null;
            $candidate->update(['state' => 'PROCESSING']);
            return $candidate->fresh();
        });
        if ($export === null) return;

        $temporary = tempnam(sys_get_temp_dir(), 'rinos-drive-export-');
        if ($temporary === false) throw new RuntimeException('Unable to create export staging file.');

        try {
            $archive = new ZipArchive();
            if ($archive->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to open export archive.');
            $principal = User::query()->findOrFail($export->idRequestingUser);
            $target = $export->workspaceScope === 'TENANT' ? DriveWorkspaceTarget::work((int) $export->idTenant) : DriveWorkspaceTarget::personal($principal->id);
            $added = [];
            $reservedPaths = [];
            $temporaryFiles = [];
            $totalBytes = 0;
            $maximumBytes = (int) config('file-storage.workspaceExport.maximumBytes', 1073741824);
            foreach ($export->selectionManifest as $item) {
                $this->appendSelection($archive, $principal, $target, $item, '', $added, $reservedPaths, $temporaryFiles, $totalBytes, $maximumBytes);
            }
            $archive->close();
            $stream = fopen($temporary, 'rb');
            if (! is_resource($stream)) throw new RuntimeException('Unable to read completed export.');
            $key = 'workspace-exports/'.$export->publicId.'.zip';
            try { if ($this->backends->disk()->writeStream($key, $stream) !== true) throw new RuntimeException('Unable to persist export.'); }
            finally { fclose($stream); }
            $export->update(['state' => 'READY', 'storageKey' => $key, 'storedSizeBytes' => filesize($temporary) ?: 0]);
        } catch (Throwable $exception) {
            $export->update(['state' => 'FAILED', 'failureCode' => 'DRIVE_EXPORT_GENERATION_FAILED']);
            throw $exception;
        } finally {
            foreach ($temporaryFiles ?? [] as $temporaryFile) @unlink($temporaryFile);
            @unlink($temporary);
        }
    }

    /**
     * @param array{type: string, id: int} $item
     * @param array<int, bool> $added
     * @param array<string, bool> $reservedPaths
     * @param list<string> $temporaryFiles
     */
    private function appendSelection(ZipArchive $archive, User $principal, DriveWorkspaceTarget $target, array $item, string $prefix, array &$added, array &$reservedPaths, array &$temporaryFiles, int &$totalBytes, int $maximumBytes): void
    {
        if ($item['type'] === 'file') {
            $this->appendFile($archive, $principal, $target, $item['id'], $prefix, $added, $reservedPaths, $temporaryFiles, $totalBytes, $maximumBytes);
            return;
        }
        $folder = WorkspaceFolder::query()->findOrFail($item['id']);
        $this->projections->details($principal, $target, 'folder', $folder->id);
        $folderPrefix = $this->reserveZipPath($prefix.$this->zipName($folder->displayName), $reservedPaths).'/';
        if ($archive->addEmptyDir(rtrim($folderPrefix, '/')) === false) throw new RuntimeException('Unable to append export folder.');
        $ownerColumn = $target->ownerType->value === 'USER' ? 'idUser' : 'idTenant';
        $ownerId = $target->scope->value === 'PERSONAL' ? $folder->idUser : $target->ownerId;
        foreach (StoredFilePossession::query()->where($ownerColumn, $ownerId)->where('idWorkspaceFolder', $folder->id)->where('state', 'ACTIVE')->get() as $possession) {
            $this->appendFile($archive, $principal, $target, $possession->id, $folderPrefix, $added, $reservedPaths, $temporaryFiles, $totalBytes, $maximumBytes);
        }
        foreach (WorkspaceFolder::query()->where($ownerColumn, $ownerId)->where('idParentFolder', $folder->id)->where('state', 'ACTIVE')->get() as $child) {
            $this->appendSelection($archive, $principal, $target, ['type' => 'folder', 'id' => $child->id], $folderPrefix, $added, $reservedPaths, $temporaryFiles, $totalBytes, $maximumBytes);
        }
    }

    /**
     * @param array<int, bool> $added
     * @param array<string, bool> $reservedPaths
     * @param list<string> $temporaryFiles
     */
    private function appendFile(ZipArchive $archive, User $principal, DriveWorkspaceTarget $target, int $possessionId, string $prefix, array &$added, array &$reservedPaths, array &$temporaryFiles, int &$totalBytes, int $maximumBytes): void
    {
        if (isset($added[$possessionId])) return;
        $item = $this->projections->details($principal, $target, 'file', $possessionId)['item'];
        $possession = StoredFilePossession::query()->findOrFail($possessionId);
        $logicalSize = (int) ($item['logicalSizeBytes'] ?? 0);
        if ($logicalSize < 0 || $totalBytes + $logicalSize > $maximumBytes) throw new DriveWorkspaceCommandException('DRIVE_EXPORT_LIMIT_EXCEEDED');
        $read = $this->privateReads->authorize(new FilePrivateReadRequest(
            $target->ownerType,
            $target->scope->value === 'PERSONAL' ? (int) $possession->idUser : $target->ownerId,
            $possessionId,
            null,
            $principal->id,
            false,
        ));
        $stream = $read->openStream();
        $temporary = tempnam(sys_get_temp_dir(), 'rinos-drive-zip-entry-');
        if ($temporary === false) {
            fclose($stream);
            throw new RuntimeException('Unable to create export entry staging file.');
        }
        try {
            $destination = fopen($temporary, 'wb');
            if (! is_resource($destination)) throw new RuntimeException('Unable to stage export file.');
            try {
                if (stream_copy_to_stream($stream, $destination) === false) throw new RuntimeException('Unable to stage export file.');
            } finally {
                fclose($destination);
                fclose($stream);
            }
            $zipPath = $this->reserveZipPath($prefix.$this->zipName((string) $item['displayName']), $reservedPaths);
            if ($archive->addFile($temporary, $zipPath) === false) throw new RuntimeException('Unable to append export file.');
            $temporaryFiles[] = $temporary;
        } catch (Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        }
        $added[$possessionId] = true;
        $totalBytes += $logicalSize;
    }

    private function zipName(string $name): string { return str_replace(['/', '\\', "\0"], '_', $name); }

    /** @param array<string, bool> $reservedPaths */
    private function reserveZipPath(string $path, array &$reservedPaths): string
    {
        $candidate = $path;
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $suffix = 2;
        while (isset($reservedPaths[$candidate])) {
            $name = $filename.' ('.$suffix++.')'.($extension === '' ? '' : '.'.$extension);
            $candidate = ($directory === '.' ? '' : $directory.'/').$name;
        }
        $reservedPaths[$candidate] = true;

        return $candidate;
    }

    /** @param array{type: string, id: int} $item */
    private function assertReadable(User $principal, DriveWorkspaceTarget $target, array $item): void
    {
        $this->projections->details($principal, $target, $item['type'], $item['id']);
    }
}
