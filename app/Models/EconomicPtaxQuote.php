<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EconomicPtaxQuote extends Model
{
    protected $table = 'economicPtaxQuote';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idEconomicIndicatorSeries', 'referenceDate', 'quotedAt', 'buyRate', 'sellRate', 'midRate', 'sourceIdentity', 'revision', 'currentKey', 'capturedAt'];

    protected function casts(): array
    {
        return ['referenceDate' => 'date', 'quotedAt' => 'datetime', 'capturedAt' => 'datetime', 'buyRate' => 'decimal:12', 'sellRate' => 'decimal:12', 'midRate' => 'decimal:12'];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(EconomicIndicatorSeries::class, 'idEconomicIndicatorSeries');
    }
}
