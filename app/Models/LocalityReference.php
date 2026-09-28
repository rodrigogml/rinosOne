<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalityReference extends Model
{
    protected $table = 'localityReference';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idCountry',
        'idBrazilState',
        'idBrazilMunicipality',
        'localityKind',
        'streetType',
        'streetName',
        'neighborhoodName',
        'cityNameObserved',
        'stateCodeObserved',
        'status',
        'removalReason',
        'removedAt',
    ];

    protected function casts(): array
    {
        return [
            'removedAt' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'ACTIVE');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'idCountry');
    }

    public function brazilState(): BelongsTo
    {
        return $this->belongsTo(BrazilState::class, 'idBrazilState');
    }

    public function brazilMunicipality(): BelongsTo
    {
        return $this->belongsTo(BrazilMunicipality::class, 'idBrazilMunicipality');
    }

    public function postalCodes(): BelongsToMany
    {
        return $this->belongsToMany(
            PostalCode::class,
            'postalCodeLocalityReference',
            'idLocalityReference',
            'idPostalCode',
        )->withPivot('createdAt');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(LocalityReferenceObservation::class, 'idLocalityReference');
    }
}
