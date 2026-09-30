<?php

namespace App\Models;

use App\Domain\Person\PersonPixKeyType;
use App\Domain\Person\PersonStatus;
use App\Models\Tenant\TenantScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonPixKey extends TenantScopedModel
{
    protected $table = 'personPixKey';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idPerson', 'keyType', 'keyValue', 'normalizedKeyValue', 'status'];

    protected function casts(): array
    {
        return [
            'keyType' => PersonPixKeyType::class,
            'status' => PersonStatus::class,
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPerson');
    }
}
