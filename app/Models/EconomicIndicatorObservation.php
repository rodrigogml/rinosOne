<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EconomicIndicatorObservation extends Model
{
    protected $table = 'economicIndicatorObservation';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idEconomicIndicatorSeries', 'referenceDate', 'value', 'accumulatedValue', 'sourceIdentity', 'revision', 'currentKey', 'publishedAt', 'capturedAt'];

    protected function casts(): array
    {
        return [
            'referenceDate' => 'date',
            'publishedAt' => 'datetime',
            'capturedAt' => 'datetime',
            'value' => 'decimal:12',
            'accumulatedValue' => 'decimal:18',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(EconomicIndicatorSeries::class, 'idEconomicIndicatorSeries');
    }
}
