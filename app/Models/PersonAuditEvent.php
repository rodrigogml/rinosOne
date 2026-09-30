<?php

namespace App\Models;

use App\Domain\Person\PersonAuditAction;
use App\Models\Tenant\TenantScopedModel;

class PersonAuditEvent extends TenantScopedModel
{
    protected $table = 'personAuditEvent';

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = ['personId', 'idActorUser', 'action', 'occurredAt', 'correlationId'];

    protected function casts(): array
    {
        return [
            'action' => PersonAuditAction::class,
            'occurredAt' => 'datetime',
        ];
    }
}
