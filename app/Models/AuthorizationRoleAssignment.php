<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationRoleAssignment extends Model
{
    protected $table = 'auth_role_assignment';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idRole', 'idUser', 'idTenant', 'state'];
}
