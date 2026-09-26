<?php

namespace App\Models;

use App\Domain\Tenant\TenantMembershipState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    protected $table = 'tenantMembership';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idTenant',
        'idUser',
        'state',
        'lastContextSelectedAt',
    ];

    protected function casts(): array
    {
        return [
            'state' => TenantMembershipState::class,
            'lastContextSelectedAt' => 'datetime',
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
}
