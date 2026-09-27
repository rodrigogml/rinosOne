<?php

namespace App\Models;

use App\Domain\Tenant\TenantState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $table = 'tenant';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'displayName',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => TenantState::class,
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'idTenant');
    }

    public function provisioning(): HasOne
    {
        return $this->hasOne(TenantProvisioning::class, 'idTenant');
    }
}
