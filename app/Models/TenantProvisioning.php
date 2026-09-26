<?php

namespace App\Models;

use App\Domain\Tenant\TenantProvisioningState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProvisioning extends Model
{
    protected $table = 'tenantProvisioning';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idTenant',
        'idRequestedByUser',
        'idempotencyKey',
        'state',
        'attemptCount',
        'lastFailureCode',
        'completedAt',
    ];

    protected function casts(): array
    {
        return [
            'state' => TenantProvisioningState::class,
            'attemptCount' => 'integer',
            'completedAt' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idRequestedByUser');
    }

}
