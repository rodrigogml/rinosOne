<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceExecutionHistory extends Model
{
    protected $table = 'maintenanceExecutionHistory';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'routineKey',
        'triggerType',
        'state',
        'startedAt',
        'completedAt',
        'summary',
        'details',
        'expiresAt',
    ];

    protected function casts(): array
    {
        return [
            'startedAt' => 'datetime',
            'completedAt' => 'datetime',
            'details' => 'array',
            'expiresAt' => 'datetime',
        ];
    }
}
