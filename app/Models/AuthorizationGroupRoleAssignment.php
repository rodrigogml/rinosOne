<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationGroupRoleAssignment extends Model
{
    protected $table = 'auth_group_role_assignment';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idRole', 'idGroup', 'idTenant', 'state'];
}
