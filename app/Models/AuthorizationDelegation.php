<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationDelegation extends Model
{
    protected $table = 'auth_delegation';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idDelegatorUser', 'idRecipientUser', 'idPermission', 'idTenant', 'scope', 'originType', 'originId', 'limits', 'startsAt', 'endsAt', 'state'];

    protected function casts(): array
    {
        return ['limits' => 'array', 'startsAt' => 'datetime', 'endsAt' => 'datetime'];
    }
}
