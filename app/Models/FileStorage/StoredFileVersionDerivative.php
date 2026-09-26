<?php

namespace App\Models\FileStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFileVersionDerivative extends Model
{
    protected $table = 'file_versionDerivative';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idSourceFileVersion',
        'idFileContent',
        'derivativeKind',
        'derivativeKey',
        'state',
    ];

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(StoredFileVersion::class, 'idSourceFileVersion');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(StoredFileContent::class, 'idFileContent');
    }
}
