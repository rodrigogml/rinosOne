<?php

namespace App\Models;

use App\Domain\Person\PersonRelationshipType;
use App\Models\Tenant\TenantScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonRelationship extends TenantScopedModel
{
    protected $table = 'personRelationship';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idSourcePerson', 'idTargetPerson', 'relationshipType', 'description'];

    protected function casts(): array
    {
        return ['relationshipType' => PersonRelationshipType::class];
    }

    public function sourcePerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idSourcePerson');
    }

    public function targetPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idTargetPerson');
    }
}
