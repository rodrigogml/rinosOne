<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationPolicy extends Model
{
    protected $table = 'auth_policy';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idTenant', 'scope', 'key', 'contextFingerprint', 'type', 'version', 'definition', 'active'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'version' => 'integer', 'active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }
}
