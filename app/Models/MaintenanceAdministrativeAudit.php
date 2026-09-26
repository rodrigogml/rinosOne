<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MaintenanceAdministrativeAudit extends Model
{
    protected $table = 'maintenanceAdministrativeAudit';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idPerformedByUser',
        'routineKey',
        'action',
        'outcome',
        'parameters',
        'occurredAt',
        'expiresAt',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'occurredAt' => 'datetime',
            'expiresAt' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Maintenance administrative audits are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Maintenance administrative audits can only expire through retention cleanup.');
        });
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idPerformedByUser');
    }
}
