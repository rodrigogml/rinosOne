<?php

namespace App\Models\FileStorage;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StoredFilePossession extends Model
{
    protected $table = 'file_filePossession';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idFile',
        'idCurrentFileVersion',
        'idUser',
        'idTenant',
        'idWorkspaceFolder',
        'storageArea',
        'purpose',
        'displayName',
        'state',
        'logicalSizeBytes',
        'trashedAt',
        'purgeAfter',
        'releasedAt',
    ];

    protected function casts(): array
    {
        return [
            'trashedAt' => 'datetime',
            'purgeAfter' => 'datetime',
            'releasedAt' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $possession): void {
            if (($possession->idUser === null) === ($possession->idTenant === null)) {
                throw new LogicException('A file possession must have exactly one owner.');
            }
            if ($possession->idWorkspaceFolder !== null) {
                $folder = WorkspaceFolder::query()->findOrFail($possession->idWorkspaceFolder);
                if ($possession->storageArea !== 'WORKSPACE' || $folder->idUser !== $possession->idUser || $folder->idTenant !== $possession->idTenant) {
                    throw new LogicException('A file possession folder must belong to the same workspace.');
                }
            }
        });
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'idFile');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(StoredFileVersion::class, 'idCurrentFileVersion');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }
}
