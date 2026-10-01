<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicIndicatorSeries extends Model
{
    protected $table = 'economicIndicatorSeries';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['code', 'kind', 'name', 'sourceKey', 'sourceSeriesCode', 'periodicity', 'unit', 'accumulationMode', 'firstReferenceDate', 'active'];

    protected function casts(): array
    {
        return ['firstReferenceDate' => 'date', 'active' => 'boolean'];
    }
}
