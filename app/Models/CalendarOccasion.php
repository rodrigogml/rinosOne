<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Persisted global definition used to calculate calendar occasions. */
class CalendarOccasion extends Model
{
    protected $table = 'calendarOccasion';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'oneTimeDate' => 'date:Y-m-d',
            'validFrom' => 'date:Y-m-d',
            'validTo' => 'date:Y-m-d',
            'fixedMonth' => 'integer',
            'fixedDay' => 'integer',
            'weekMonth' => 'integer',
            'easterOffsetDays' => 'integer',
        ];
    }
}
