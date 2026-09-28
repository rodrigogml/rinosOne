<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationPolicy;
use App\Models\AuthorizationPolicyBinding;
use App\Models\Tenant;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationPolicyService
{
    public function __construct(
        private readonly PolicyDefinitionValidator $validator,
        private readonly AuthorizationAuditLogger $audit,
        private readonly PolicyVersionService $policyVersions,
    ) {}

    /** @param array<string, mixed> $definition */
    public function publish(string $key, AuthorizationScope $scope, ?int $tenantId, array $definition, ?int $actorUserId = null, ?string $correlationId = null): AuthorizationPolicy
    {
        $key = trim($key);
        if ($key === '' || mb_strlen($key) > 160) {
            throw new LogicException('The authorization policy key is invalid.');
        }
        $definition = $this->validator->validate($definition);

        return DB::transaction(function () use ($key, $scope, $tenantId, $definition, $actorUserId, $correlationId): AuthorizationPolicy {
            $this->validateContext($scope, $tenantId);
            $fingerprint = $scope === AuthorizationScope::Tenant ? "tenant:{$tenantId}" : strtolower($scope->value);
            $version = (int) AuthorizationPolicy::query()->where('scope', $scope->value)->where('contextFingerprint', $fingerprint)->where('key', $key)->lockForUpdate()->max('version') + 1;
            $policy = AuthorizationPolicy::query()->create(['idTenant' => $tenantId, 'scope' => $scope->value, 'key' => $key, 'contextFingerprint' => $fingerprint, 'type' => 'COMPOSITE', 'version' => $version, 'definition' => $definition, 'active' => true]);
            $this->audit->record('authorization.policy.published', 'authorization.policy', $policy->id, actorUserId: $actorUserId, tenantId: $tenantId, after: ['key' => $key, 'scope' => $scope->value, 'version' => $version, 'active' => true], correlationId: $correlationId);
            $this->policyVersions->invalidate($scope, $tenantId);

            return $policy;
        });
    }

    public function bind(AuthorizationPolicy $policy, AuthorizationPermission $permission, ?int $actorUserId = null, ?string $correlationId = null): AuthorizationPolicyBinding
    {
        return DB::transaction(function () use ($policy, $permission, $actorUserId, $correlationId): AuthorizationPolicyBinding {
            $policy = AuthorizationPolicy::query()->lockForUpdate()->findOrFail($policy->id);
            $permission = AuthorizationPermission::query()->lockForUpdate()->findOrFail($permission->id);
            if (! $policy->active || ! $permission->active || $policy->scope !== $permission->scope) {
                throw new LogicException('An authorization policy binding requires active entities from the same scope.');
            }
            $binding = AuthorizationPolicyBinding::query()->firstOrCreate(['idPolicy' => $policy->id, 'idPermission' => $permission->id, 'idRole' => null, 'idResourceType' => null, 'resourceId' => null], ['active' => true]);
            $this->audit->record('authorization.policy.bound', 'authorization.policy_binding', $binding->id, actorUserId: $actorUserId, tenantId: $policy->idTenant, after: ['idPolicy' => $policy->id, 'idPermission' => $permission->id, 'active' => $binding->active], correlationId: $correlationId);
            $this->policyVersions->invalidate(AuthorizationScope::from($policy->scope), $policy->idTenant);

            return $binding;
        });
    }

    private function validateContext(AuthorizationScope $scope, ?int $tenantId): void
    {
        if ($scope !== AuthorizationScope::Tenant && $tenantId !== null) {
            throw new LogicException('A non-tenant authorization policy cannot receive a tenant context.');
        }
        if ($scope === AuthorizationScope::Tenant && ($tenantId === null || ! Tenant::query()->whereKey($tenantId)->where('state', TenantState::Active)->lockForUpdate()->exists())) {
            throw new LogicException('A tenant authorization policy requires an active tenant context.');
        }
    }
}
