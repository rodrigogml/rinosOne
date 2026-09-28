<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationPolicyBinding extends Model
{
    protected $table = 'auth_policy_binding';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idPolicy', 'idPermission', 'idRole', 'idResourceType', 'resourceId', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AuthorizationPolicy::class, 'idPolicy');
    }
}
