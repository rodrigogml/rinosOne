<?php

namespace App\Models;

use App\Domain\Tenant\TenantState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $table = 'tenant';

    protected $keyType = 'string';

    public $incrementing = false;

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

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            $tenant->id ??= (string) Str::ulid();
        });
    }
}
