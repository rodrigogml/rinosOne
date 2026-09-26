<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationResourceRelation extends Model
{
    protected $table = 'auth_resource_relation';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idResourceType', 'resourceId', 'idUser', 'idGroup', 'idTenant', 'relationKey', 'scope', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
