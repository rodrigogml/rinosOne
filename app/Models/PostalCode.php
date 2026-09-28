<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PostalCode extends Model
{
    protected $table = 'postalCode';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idCountry',
        'normalizedValue',
        'displayValue',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'idCountry');
    }

    public function localityReferences(): BelongsToMany
    {
        return $this->belongsToMany(
            LocalityReference::class,
            'postalCodeLocalityReference',
            'idPostalCode',
            'idLocalityReference',
        )->withPivot('createdAt');
    }
}
