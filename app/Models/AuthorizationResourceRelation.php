<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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

    protected static function booted(): void
    {
        static::saving(function (self $relation): void {
            if (($relation->idUser === null) === ($relation->idGroup === null)) {
                throw new LogicException('A resource relation must have exactly one subject.');
            }
        });
    }
}
