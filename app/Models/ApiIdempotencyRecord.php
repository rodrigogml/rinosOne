<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiIdempotencyRecord extends Model
{
    protected $table = 'apiIdempotencyRecord';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idUser',
        'idTenant',
        'tenantScopeKey',
        'operation',
        'idempotencyKey',
        'requestFingerprint',
        'state',
        'responseStatus',
        'responseContentType',
        'responseBody',
        'expiresAt',
    ];

    protected function casts(): array
    {
        return [
            'responseStatus' => 'integer',
            'expiresAt' => 'datetime',
        ];
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
