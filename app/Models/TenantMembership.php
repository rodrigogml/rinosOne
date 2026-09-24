<?php

namespace App\Models;

use App\Domain\Tenant\TenantMembershipRole;
use App\Domain\Tenant\TenantMembershipState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantMembership extends Model
{
    protected $table = 'tenantMembership';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idTenant',
        'idUser',
        'role',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'role' => TenantMembershipRole::class,
            'state' => TenantMembershipState::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            $membership->id ??= (string) Str::ulid();
        });
    }
}
