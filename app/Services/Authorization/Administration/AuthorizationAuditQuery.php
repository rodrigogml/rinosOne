<?php

namespace App\Services\Authorization\Administration;

use App\Models\AuthorizationAuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Provides a tenant-scoped, redacted read model for immutable authorization audit events. */
class AuthorizationAuditQuery
{
    /** @return LengthAwarePaginator<int, AuthorizationAuditEvent> */
    public function forTenant(int $tenantId, ?string $operation, ?string $targetType, ?int $targetId, int $perPage): LengthAwarePaginator
    {
        return AuthorizationAuditEvent::query()->where('idTenant', $tenantId)
            ->when($operation !== null, fn ($query) => $query->where('operation', $operation))
            ->when($targetType !== null, fn ($query) => $query->where('targetType', $targetType))
            ->when($targetId !== null, fn ($query) => $query->where('targetId', $targetId))
            ->orderByDesc('occurredAt')->orderByDesc('id')->paginate($perPage);
    }
}
