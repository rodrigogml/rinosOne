<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrazilState extends Model
{
    protected $table = 'brazilState';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idCountry',
        'ibgeCode',
        'abbreviation',
        'name',
        'activeForSelection',
    ];

    protected function casts(): array
    {
        return [
            'activeForSelection' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'idCountry');
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(BrazilMunicipality::class, 'idBrazilState');
    }

    public function localityReferences(): HasMany
    {
        return $this->hasMany(LocalityReference::class, 'idBrazilState');
    }
}
