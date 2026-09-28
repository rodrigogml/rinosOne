<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationServiceCredential extends Model
{
    protected $table = 'auth_service_credential';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idServiceIdentity', 'displayName', 'publicId', 'secretHash', 'permissionKeys', 'state', 'expiresAt', 'lastUsedAt'];

    protected function casts(): array
    {
        return ['permissionKeys' => 'array', 'expiresAt' => 'datetime', 'lastUsedAt' => 'datetime'];
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(AuthorizationServiceIdentity::class, 'idServiceIdentity');
    }
}
