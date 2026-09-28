<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationRestriction extends Model
{
    protected $table = 'auth_restriction';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idPermission', 'idResourceType', 'resourceId', 'idUser', 'idGroup', 'idTenant', 'scope', 'startsAt', 'endsAt', 'active'];

    protected function casts(): array
    {
        return ['startsAt' => 'datetime', 'endsAt' => 'datetime', 'active' => 'boolean'];
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(AuthorizationPermission::class, 'idPermission');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AuthorizationGroup::class, 'idGroup');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }

    public function isEffectiveAt(DateTimeInterface $at): bool
    {
        return $this->active
            && ($this->startsAt === null || $this->startsAt <= $at)
            && ($this->endsAt === null || $this->endsAt > $at);
    }
}
