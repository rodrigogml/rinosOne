<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantSecurityEvent;
use App\Domain\Tenant\TenantState;
use App\Http\Requests\Tenant\ChangeTenantAvailabilityRequest;
use App\Http\Requests\Tenant\CreateTenantRequest;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\TenantCapabilityProjection;
use App\Services\Tenant\TenantContextResolver;
use App\Services\Tenant\TenantContextService;
use App\Services\Tenant\TenantCreationService;
use App\Services\Tenant\TenantSecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TenantController
{
    public function index(TenantCapabilityProjection $capabilities): JsonResponse
    {
        $memberships = TenantMembership::query()
            ->with('tenant')
            ->where('idUser', request()->user()->id)
            ->where('state', TenantMembershipState::Active)
            ->orderByDesc('lastContextSelectedAt')
            ->orderByDesc('updatedAt')
            ->get();

        return response()->json(['tenants' => $memberships->map(fn (TenantMembership $membership): array => $this->tenant($membership->tenant, request()->user(), $capabilities))->values()]);
    }

    public function store(CreateTenantRequest $request, TenantCreationService $creation, TenantCapabilityProjection $capabilities): JsonResponse
    {
        $validated = $request->validated();
        $result = $creation->create($request->user(), $validated['displayName'], $validated['idempotencyKey']);

        return response()->json([
            'tenant' => $this->tenant($result->tenant, $request->user(), $capabilities),
            'provisioning' => ['id' => $result->provisioning->id, 'state' => $result->provisioning->state->value],
        ], 202);
    }

    public function startContext(string $tenantId, TenantContextService $contexts, TenantContextResolver $resolver, TenantSecurityEventLogger $securityEvents, TenantCapabilityProjection $capabilities): JsonResponse
    {
        $membership = $resolver->resolveMembership(request()->user(), $tenantId);

        if ($membership === null) {
            $securityEvents->record(TenantSecurityEvent::ContextDenied, request()->user()->id);

            return $this->notAvailable();
        }

        $membership->update(['lastContextSelectedAt' => now()]);
        $securityEvents->record(TenantSecurityEvent::ContextSelected, request()->user()->id);

        return response()->json(['context' => $contexts->context($membership, $capabilities->forTenant(request()->user(), $membership->tenant->id))]);
    }

    public function endContext(string $tenantId, TenantContextService $contexts, TenantContextResolver $resolver, TenantSecurityEventLogger $securityEvents): Response|JsonResponse
    {
        if ($resolver->resolveMembership(request()->user(), $tenantId) === null) {
            $securityEvents->record(TenantSecurityEvent::ContextDenied, request()->user()->id);

            return $this->notAvailable();
        }

        $securityEvents->record(TenantSecurityEvent::ContextEnded, request()->user()->id);

        return response()->noContent();
    }

    public function changeAvailability(string $tenantId, ChangeTenantAvailabilityRequest $request, AuthorizationService $authorization, TenantSecurityEventLogger $securityEvents, TenantCapabilityProjection $capabilities): JsonResponse
    {
        $membership = TenantMembership::query()->with('tenant')
            ->where('idUser', $request->user()->id)
            ->where('idTenant', $tenantId)
            ->where('state', TenantMembershipState::Active)
            ->first();

        if ($membership === null) {
            return $this->notAvailable();
        }

        if (! $authorization->check($request->user(), 'tenant.availability.manage', AuthorizationScope::Tenant, (int) $tenantId)->allowed) {
            return response()->json(['error' => ['code' => 'TENANT_ADMINISTRATOR_REQUIRED', 'message' => 'Ação não permitida para este tenant.']], 403);
        }

        $state = TenantState::from($request->string('state')->toString());

        if ($membership->tenant->state === TenantState::Provisioning || $membership->tenant->state === TenantState::Failed) {
            return response()->json(['error' => ['code' => 'TENANT_STATE_CONFLICT', 'message' => 'O tenant não pode ser alterado neste estado.']], 409);
        }

        $membership->tenant->update(['state' => $state]);
        $securityEvents->record(TenantSecurityEvent::AvailabilityChanged, $request->user()->id);

        return response()->json(['tenant' => $this->tenant($membership->tenant->refresh(), $request->user(), $capabilities)]);
    }

    private function notAvailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'TENANT_NOT_AVAILABLE', 'message' => 'O tenant não está disponível.']], 404);
    }

    /** @return array{id: int, displayName: string, state: string, selectable: bool, canManageAvailability: bool} */
    private function tenant(Tenant $tenant, User $principal, TenantCapabilityProjection $capabilities): array
    {
        return [
            'id' => $tenant->id,
            'displayName' => $tenant->displayName,
            'state' => $tenant->state->value,
            'selectable' => $tenant->state === TenantState::Active,
            ...$capabilities->forTenant($principal, $tenant->id),
        ];
    }
}
