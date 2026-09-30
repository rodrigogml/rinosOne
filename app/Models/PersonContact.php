<?php

namespace App\Models;

use App\Domain\Person\PersonContactType;
use App\Models\Tenant\TenantScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonContact extends TenantScopedModel
{
    protected $table = 'personContact';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idPerson', 'contactType', 'value', 'normalizedValue', 'description'];

    protected function casts(): array
    {
        return ['contactType' => PersonContactType::class];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPerson');
    }
}
