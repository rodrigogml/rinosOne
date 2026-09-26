<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFileVersionMetadata extends Model
{
    protected $table = 'file_versionMetadata';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idFileVersion',
        'metadataKey',
        'metadataValue',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'metadataValue' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(StoredFileVersion::class, 'idFileVersion');
    }
}
