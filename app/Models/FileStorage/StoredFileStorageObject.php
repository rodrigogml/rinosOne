<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFileStorageObject extends Model
{
    protected $table = 'file_storageObject';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idFileContent',
        'idStorageBackend',
        'storedSha256',
        'storageKey',
        'encoding',
        'storedSizeBytes',
        'state',
        'retentionUntil',
    ];

    protected function casts(): array
    {
        return [
            'retentionUntil' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(StoredFileContent::class, 'idFileContent');
    }

    public function backend(): BelongsTo
    {
        return $this->belongsTo(StoredFileStorageBackend::class, 'idStorageBackend');
    }
}
