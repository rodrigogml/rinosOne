<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WorkspaceFolder extends Model
{
    protected $table = 'file_workspaceFolder';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idParentFolder', 'idUser', 'idTenant', 'displayName', 'state', 'trashedAt', 'purgeAfter'];

    protected function casts(): array
    {
        return [
            'trashedAt' => 'datetime',
            'purgeAfter' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $folder): void {
            if (($folder->idUser === null) === ($folder->idTenant === null)) {
                throw new LogicException('A workspace folder must have exactly one owner.');
            }

            if ($folder->idParentFolder !== null) {
                $visitedFolderIds = [];
                $parentId = $folder->idParentFolder;

                while ($parentId !== null) {
                    if (isset($visitedFolderIds[$parentId]) || $parentId === $folder->id) {
                        throw new LogicException('A workspace folder cannot be moved below one of its descendants.');
                    }

                    $visitedFolderIds[$parentId] = true;
                    $parent = self::query()->findOrFail($parentId);
                    if ($parent->idUser !== $folder->idUser || $parent->idTenant !== $folder->idTenant) {
                        throw new LogicException('A workspace folder parent must belong to the same workspace.');
                    }

                    $parentId = $parent->idParentFolder;
                }
            }

            if ($folder->state !== 'ACTIVE') {
                return;
            }

            $sameNameSibling = self::query()
                ->where('idUser', $folder->idUser)
                ->where('idTenant', $folder->idTenant)
                ->where('idParentFolder', $folder->idParentFolder)
                ->where('displayName', $folder->displayName)
                ->where('state', 'ACTIVE')
                ->when($folder->id !== null, fn ($query) => $query->whereKeyNot($folder->id))
                ->exists();

            if ($sameNameSibling) {
                throw new LogicException('A workspace folder name must be unique among active siblings.');
            }
        });
    }
}
