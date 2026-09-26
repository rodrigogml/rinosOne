<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredFileStorageBackend extends Model
{
    protected $table = 'file_storageBackend';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'backendKey',
        'state',
    ];

    public function objects(): HasMany
    {
        return $this->hasMany(StoredFileStorageObject::class, 'idStorageBackend');
    }
}
