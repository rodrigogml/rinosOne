<?php

namespace App\Models;

use App\Domain\Tenant\TenantSchemaUpdateState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSchemaUpdate extends Model
{
    protected $table = 'tenantSchemaUpdate';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idTenant',
        'targetCatalog',
        'state',
        'attemptCount',
        'lastFailureCode',
        'startedAt',
        'completedAt',
    ];

    protected function casts(): array
    {
        return [
            'state' => TenantSchemaUpdateState::class,
            'attemptCount' => 'integer',
            'startedAt' => 'datetime',
            'completedAt' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }
}
