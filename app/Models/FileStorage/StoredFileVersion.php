<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoredFileVersion extends Model
{
    protected $table = 'file_fileVersion';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idFile',
        'idParentFileVersion',
        'idFileContent',
        'versionNumber',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'idFile');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'idParentFileVersion');
    }

    public function childVersions(): HasMany
    {
        return $this->hasMany(self::class, 'idParentFileVersion');
    }

    public function currentPossessions(): HasMany
    {
        return $this->hasMany(StoredFilePossession::class, 'idCurrentFileVersion')
            ->whereIn('state', ['ACTIVE', 'TRASHED']);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(StoredFileContent::class, 'idFileContent');
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(StoredFileVersionMetadata::class, 'idFileVersion');
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(StoredFileVersionDerivative::class, 'idSourceFileVersion');
    }
}
