<?php

namespace App\Models\FileStorage;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StoredFileOwnerUsage extends Model
{
    protected $table = 'file_ownerUsage';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idUser',
        'idTenant',
        'workspaceBytes',
        'systemManagedBytes',
        'trashBytes',
        'totalBytes',
    ];

    protected function casts(): array
    {
        return [
            'workspaceBytes' => 'integer',
            'systemManagedBytes' => 'integer',
            'trashBytes' => 'integer',
            'totalBytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $usage): void {
            if (($usage->idUser === null) === ($usage->idTenant === null)) {
                throw new LogicException('File owner usage must have exactly one owner.');
            }
        });
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
