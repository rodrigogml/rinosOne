<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use Illuminate\Support\Str;

/** Resolves a deterministic safe name while the caller holds its workspace lock. */
class DriveWorkspaceNameResolver
{
    public function normalize(string $displayName): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $displayName));

        if ($normalized === '' || mb_strlen($normalized) > 160 || in_array($normalized, ['.', '..'], true)
            || str_contains($normalized, '/') || str_contains($normalized, '\\') || preg_match('/[\x00-\x1F\x7F]/u', $normalized) === 1) {
            throw new DriveWorkspaceCommandException('DRIVE_INVALID_NAME');
        }

        return $normalized;
    }

    public function resolve(DriveWorkspaceTarget $target, ?int $parentFolderId, string $requestedName, ?int $ignoredFolderId = null, ?int $ignoredPossessionId = null): string
    {
        $requestedName = $this->normalize($requestedName);
        $ownerColumn = $target->ownerType->value === 'USER' ? 'idUser' : 'idTenant';
        $folderNames = WorkspaceFolder::query()
            ->where($ownerColumn, $target->ownerId)
            ->whereNull($target->ownerType->value === 'USER' ? 'idTenant' : 'idUser')
            ->where('idParentFolder', $parentFolderId)
            ->where('state', 'ACTIVE')
            ->when($ignoredFolderId !== null, fn ($query) => $query->whereKeyNot($ignoredFolderId))
            ->lockForUpdate()
            ->pluck('displayName');
        $fileNames = StoredFilePossession::query()
            ->where($ownerColumn, $target->ownerId)
            ->whereNull($target->ownerType->value === 'USER' ? 'idTenant' : 'idUser')
            ->where('storageArea', 'WORKSPACE')
            ->where('idWorkspaceFolder', $parentFolderId)
            ->where('state', 'ACTIVE')
            ->when($ignoredPossessionId !== null, fn ($query) => $query->whereKeyNot($ignoredPossessionId))
            ->lockForUpdate()
            ->pluck('displayName');
        $usedNames = $folderNames->merge($fileNames)->map(fn (string $name): string => mb_strtolower($name))->all();

        if (! in_array(mb_strtolower($requestedName), $usedNames, true)) {
            return $requestedName;
        }

        $extension = pathinfo($requestedName, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $requestedName : Str::beforeLast($requestedName, '.');
        for ($index = 2; $index < 10000; $index++) {
            $candidate = $this->normalize(sprintf('%s (%d)%s', $stem, $index, $extension === '' ? '' : '.'.$extension));
            if (! in_array(mb_strtolower($candidate), $usedNames, true)) {
                return $candidate;
            }
        }

        throw new DriveWorkspaceCommandException('DRIVE_NAME_RESOLUTION_FAILED');
    }
}
