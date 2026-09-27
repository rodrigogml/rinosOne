<?php

namespace App\Services\Authorization\Performance;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPolicyVersion;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Maintains the monotonic policy version used exclusively to invalidate
 * disposable authorization cache entries. Persistent authorization data stays
 * authoritative when the cache is absent or unavailable.
 */
class PolicyVersionService
{
    public function current(AuthorizationScope $scope, ?int $tenantId = null): int
    {
        [$tenantId, $fingerprint] = $this->context($scope, $tenantId);

        return (int) AuthorizationPolicyVersion::query()->firstOrCreate(
            ['scope' => $scope->value, 'subjectFingerprint' => $fingerprint],
            ['idTenant' => $tenantId, 'version' => 1],
        )->version;
    }

    public function invalidate(AuthorizationScope $scope, ?int $tenantId = null): int
    {
        [$tenantId, $fingerprint] = $this->context($scope, $tenantId);

        return DB::transaction(function () use ($scope, $tenantId, $fingerprint): int {
            $version = AuthorizationPolicyVersion::query()
                ->where('scope', $scope->value)
                ->where('subjectFingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($version === null) {
                return (int) AuthorizationPolicyVersion::query()->create([
                    'scope' => $scope->value,
                    'idTenant' => $tenantId,
                    'subjectFingerprint' => $fingerprint,
                    'version' => 2,
                ])->version;
            }

            $version->increment('version');

            return (int) $version->version;
        });
    }

    /** @return array{0: ?int, 1: string} */
    private function context(AuthorizationScope $scope, ?int $tenantId): array
    {
        if ($scope === AuthorizationScope::Tenant) {
            if ($tenantId === null) {
                return [null, 'tenant:global'];
            }
            if ($tenantId < 1) {
                throw new LogicException('A tenant policy version requires a positive tenant identifier.');
            }

            return [$tenantId, 'tenant:'.$tenantId];
        }

        if ($tenantId !== null) {
            throw new LogicException('A non-tenant policy version cannot receive a tenant context.');
        }

        return [null, strtolower($scope->value)];
    }
}
