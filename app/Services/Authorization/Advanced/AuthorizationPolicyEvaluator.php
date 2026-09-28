<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationEvaluationContext;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use Illuminate\Support\Facades\DB;

/** Applies active policy bindings only after the base authorization engine found an eligible grant. */
class AuthorizationPolicyEvaluator
{
    public function qualifies(string $permissionKey, AuthorizationScope $scope, ?int $tenantId, ?ResourceReference $resource, ?AuthorizationEvaluationContext $context): bool
    {
        $definitions = DB::table('auth_policy_binding')
            ->join('auth_policy', 'auth_policy.id', '=', 'auth_policy_binding.idPolicy')
            ->join('auth_permission', 'auth_permission.id', '=', 'auth_policy_binding.idPermission')
            ->where('auth_policy_binding.active', true)
            ->whereNull('auth_policy_binding.idRole')
            ->where('auth_policy.active', true)
            ->where('auth_policy.scope', $scope->value)
            ->where('auth_permission.key', $permissionKey)
            ->when($scope === AuthorizationScope::Tenant, fn ($query) => $query->where('auth_policy.idTenant', $tenantId), fn ($query) => $query->whereNull('auth_policy.idTenant'))
            ->when($resource === null, fn ($query) => $query->whereNull('auth_policy_binding.idResourceType')->whereNull('auth_policy_binding.resourceId'), function ($query) use ($resource): void {
                $query->where(function ($qualifier) use ($resource): void {
                    $qualifier->whereNull('auth_policy_binding.idResourceType')
                        ->orWhere(function ($resourceQualifier) use ($resource): void {
                            $resourceQualifier->where('auth_policy_binding.resourceId', $resource->id)
                                ->whereExists(function ($type) use ($resource): void {
                                    $type->selectRaw('1')->from('auth_resource_type')->whereColumn('auth_resource_type.id', 'auth_policy_binding.idResourceType')->where('auth_resource_type.key', $resource->type);
                                });
                        });
                });
            })
            ->pluck('auth_policy.definition');

        foreach ($definitions as $definition) {
            $decoded = json_decode($definition, true, 512, JSON_THROW_ON_ERROR);
            if (! $this->evaluateNode($decoded, $context)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $node */
    private function evaluateNode(array $node, ?AuthorizationEvaluationContext $context): bool
    {
        $operator = array_key_first($node);
        $children = $node[$operator] ?? [];
        $results = array_map(fn (array $child): bool => isset($child['type']) ? $this->evaluateCondition($child, $context) : $this->evaluateNode($child, $context), $children);

        return $operator === 'all' ? ! in_array(false, $results, true) : in_array(true, $results, true);
    }

    /** @param array<string, mixed> $condition */
    private function evaluateCondition(array $condition, ?AuthorizationEvaluationContext $context): bool
    {
        if ($context === null) {
            return false;
        }

        return match ($condition['type']) {
            'AMOUNT_MAXIMUM' => $context->amount !== null && $context->amount <= $condition['maximum'],
            'ORGANIZATIONAL_UNIT_IN' => array_intersect($context->organizationUnitIds, $condition['values']) !== [],
            'TIME_WINDOW_UTC' => $context->utcMinute !== null && $this->inTimeWindow($context->utcMinute, $condition['startMinute'], $condition['endMinute']),
            'LOGICAL_LOCATION_IN' => array_intersect($context->logicalLocations, $condition['values']) !== [],
            'SUBJECT_ATTRIBUTE_EQUALS' => $condition['attribute'] === 'departmentCode' ? $context->departmentCode === $condition['value'] : $context->employmentType === $condition['value'],
            default => false,
        };
    }

    private function inTimeWindow(int $minute, int $startMinute, int $endMinute): bool
    {
        return $startMinute < $endMinute ? $minute >= $startMinute && $minute < $endMinute : $minute >= $startMinute || $minute < $endMinute;
    }
}
