<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationResourceType extends Model
{
    protected $table = 'auth_resource_type';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['key', 'scope', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
