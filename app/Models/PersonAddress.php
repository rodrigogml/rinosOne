<?php

namespace App\Models;

use App\Domain\Person\PersonAddressType;
use App\Models\Tenant\TenantScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonAddress extends TenantScopedModel
{
    protected $table = 'personAddress';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idPerson', 'label', 'addressType', 'idCountry', 'idBrazilState', 'idBrazilMunicipality',
        'idLocalityReference', 'stateText', 'cityText', 'street', 'number', 'complement', 'district',
        'reference', 'postalCode',
    ];

    protected function casts(): array
    {
        return ['addressType' => PersonAddressType::class];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPerson');
    }
}
