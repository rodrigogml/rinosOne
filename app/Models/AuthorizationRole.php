<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationRole extends Model
{
    protected $table = 'auth_role';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idTenant', 'key', 'displayName', 'description', 'scope', 'type', 'systemManaged', 'active'];

    protected function casts(): array
    {
        return ['systemManaged' => 'boolean', 'active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }
}
