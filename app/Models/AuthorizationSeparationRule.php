<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationSeparationRule extends Model
{
    protected $table = 'auth_separation_rule';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idTenant', 'scope', 'contextFingerprint', 'idPermission', 'idIncompatiblePermission', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
