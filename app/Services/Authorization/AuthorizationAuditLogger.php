<?php

namespace App\Services\Authorization;

use App\Models\AuthorizationAuditEvent;

class AuthorizationAuditLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEY_FRAGMENTS = ['password', 'secret', 'token', 'credential', 'authorization'];

    public function record(
        string $operation,
        string $targetType,
        int $targetId,
        ?int $actorUserId = null,
        ?int $tenantId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $correlationId = null,
    ): AuthorizationAuditEvent {
        return AuthorizationAuditEvent::query()->create([
            'occurredAt' => now(),
            'idActorUser' => $actorUserId,
            'idTenant' => $tenantId,
            'operation' => $operation,
            'targetType' => $targetType,
            'targetId' => $targetId,
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
            'correlationId' => $correlationId,
        ]);
    }

    private function sanitize(?array $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        $sanitized = [];
        foreach ($snapshot as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
