<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationPolicyVersion extends Model
{
    protected $table = 'auth_policy_version';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['scope', 'idTenant', 'subjectFingerprint', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
