<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredFileContent extends Model
{
    protected $table = 'file_fileContent';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'logicalSha256',
        'logicalSizeBytes',
        'detectedMimeType',
        'declaredExtension',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(StoredFileVersion::class, 'idFileContent');
    }

    public function storageObjects(): HasMany
    {
        return $this->hasMany(StoredFileStorageObject::class, 'idFileContent');
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(StoredFileVersionDerivative::class, 'idFileContent');
    }
}
