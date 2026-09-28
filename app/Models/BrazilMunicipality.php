<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrazilMunicipality extends Model
{
    protected $table = 'brazilMunicipality';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idBrazilState',
        'ibgeCode',
        'name',
        'activeForSelection',
    ];

    protected function casts(): array
    {
        return [
            'activeForSelection' => 'boolean',
        ];
    }

    public function brazilState(): BelongsTo
    {
        return $this->belongsTo(BrazilState::class, 'idBrazilState');
    }

    public function localityReferences(): HasMany
    {
        return $this->hasMany(LocalityReference::class, 'idBrazilMunicipality');
    }
}
