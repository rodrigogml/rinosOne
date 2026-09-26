<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationPermission extends Model
{
    protected $table = 'auth_permission';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['key', 'displayName', 'description', 'scope', 'systemManaged', 'active'];

    protected function casts(): array
    {
        return ['systemManaged' => 'boolean', 'active' => 'boolean'];
    }
}
