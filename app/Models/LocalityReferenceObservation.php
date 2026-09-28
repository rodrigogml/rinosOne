<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalityReferenceObservation extends Model
{
    protected $table = 'localityReferenceObservation';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idLocalityReference',
        'sourceKey',
        'externalIdentifier',
        'identitySignature',
        'equivalenceSignature',
        'observedPayload',
        'firstSeenAt',
        'lastSeenAt',
    ];

    protected function casts(): array
    {
        return [
            'observedPayload' => 'array',
            'firstSeenAt' => 'datetime',
            'lastSeenAt' => 'datetime',
        ];
    }

    public function localityReference(): BelongsTo
    {
        return $this->belongsTo(LocalityReference::class, 'idLocalityReference');
    }
}
