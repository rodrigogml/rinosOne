<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'country';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'isoAlpha2',
        'isoAlpha3',
        'isoNumeric',
        'name',
        'activeForSelection',
    ];

    protected function casts(): array
    {
        return [
            'activeForSelection' => 'boolean',
        ];
    }

    public function brazilStates(): HasMany
    {
        return $this->hasMany(BrazilState::class, 'idCountry');
    }

    public function postalCodes(): HasMany
    {
        return $this->hasMany(PostalCode::class, 'idCountry');
    }

    public function localityReferences(): HasMany
    {
        return $this->hasMany(LocalityReference::class, 'idCountry');
    }
}
