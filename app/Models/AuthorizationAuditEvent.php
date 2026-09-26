<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuthorizationAuditEvent extends Model
{
    protected $table = 'auth_audit_event';

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = [
        'occurredAt',
        'idActorUser',
        'idTenant',
        'operation',
        'targetType',
        'targetId',
        'before',
        'after',
        'correlationId',
    ];

    protected function casts(): array
    {
        return [
            'occurredAt' => 'datetime',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Authorization audit events are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Authorization audit events can only expire through retention cleanup.');
        });
    }
}
