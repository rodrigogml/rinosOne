<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredFile extends Model
{
    protected $table = 'file_file';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'fileUuid',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(StoredFileVersion::class, 'idFile');
    }

    public function possessions(): HasMany
    {
        return $this->hasMany(StoredFilePossession::class, 'idFile');
    }
}
