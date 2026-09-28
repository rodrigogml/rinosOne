<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationSeparationRule;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationSeparationRuleService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly PolicyVersionService $policyVersions) {}

    public function create(AuthorizationPermission $first, AuthorizationPermission $second, AuthorizationScope $scope, ?int $tenantId = null, ?int $actorUserId = null): AuthorizationSeparationRule
    {
        if ($first->id === $second->id || ! $first->active || ! $second->active || $first->scope !== $scope->value || $second->scope !== $scope->value) {
            throw new LogicException('A separation rule requires two distinct active permissions from the same scope.');
        }
        if (($scope === AuthorizationScope::Tenant) !== ($tenantId !== null)) {
            throw new LogicException('The separation rule context is invalid.');
        }
        [$first, $second] = $first->id < $second->id ? [$first, $second] : [$second, $first];
        $fingerprint = $scope === AuthorizationScope::Tenant ? "tenant:{$tenantId}" : strtolower($scope->value);

        return DB::transaction(function () use ($first, $second, $scope, $tenantId, $actorUserId, $fingerprint): AuthorizationSeparationRule {
            $rule = AuthorizationSeparationRule::query()->firstOrCreate(['scope' => $scope->value, 'contextFingerprint' => $fingerprint, 'idPermission' => $first->id, 'idIncompatiblePermission' => $second->id], ['idTenant' => $tenantId, 'active' => true]);
            if ($rule->wasRecentlyCreated) {
                $this->audit->record('authorization.separation_rule.created', 'authorization.separation_rule', $rule->id, actorUserId: $actorUserId, tenantId: $tenantId, after: ['idPermission' => $first->id, 'idIncompatiblePermission' => $second->id, 'active' => true]);
                $this->policyVersions->invalidate($scope, $tenantId);
            }

            return $rule;
        });
    }
}
