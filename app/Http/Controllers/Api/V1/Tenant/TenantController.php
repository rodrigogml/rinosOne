<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantSecurityEvent;
use App\Domain\Tenant\TenantState;
use App\Http\Requests\Tenant\ChangeTenantAvailabilityRequest;
use App\Http\Requests\Tenant\CreateTenantRequest;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Tenant\TenantContextResolver;
use App\Services\Tenant\TenantContextService;
use App\Services\Tenant\TenantCreationService;
use App\Services\Tenant\TenantSecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TenantController
{
    public function index(): JsonResponse
    {
        $memberships = TenantMembership::query()
            ->with('tenant')
            ->where('idUser', request()->user()->id)
            ->where('state', TenantMembershipState::Active)
            ->orderByDesc('lastContextSelectedAt')
            ->orderByDesc('updatedAt')
            ->get();

        return response()->json(['tenants' => $memberships->map(fn (TenantMembership $membership): array => [
            'id' => $membership->tenant->id,
            'displayName' => $membership->tenant->displayName,
            'state' => $membership->tenant->state->value,
            'selectable' => $membership->tenant->state === TenantState::Active,
            'role' => $membership->role->value,
        ])->values()]);
    }

    public function store(CreateTenantRequest $request, TenantCreationService $creation): JsonResponse
    {
        $validated = $request->validated();
        $result = $creation->create($request->user(), $validated['displayName'], $validated['idempotencyKey']);

        return response()->json([
            'tenant' => $this->tenant($result->tenant),
            'provisioning' => ['id' => $result->provisioning->id, 'state' => $result->provisioning->state->value],
        ], 202);
    }

    public function startContext(string $tenantId, TenantContextService $contexts, TenantContextResolver $resolver, TenantSecurityEventLogger $securityEvents): JsonResponse
    {
        $membership = $resolver->resolveMembership(request()->user(), $tenantId);

        if ($membership === null) {
            $securityEvents->record(TenantSecurityEvent::ContextDenied, request()->user()->id);

            return $this->notAvailable();
        }

        $membership->update(['lastContextSelectedAt' => now()]);
        $securityEvents->record(TenantSecurityEvent::ContextSelected, request()->user()->id);

        return response()->json(['context' => $contexts->context($membership)]);
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

    public function changeAvailability(string $tenantId, ChangeTenantAvailabilityRequest $request, TenantContextService $contexts, TenantSecurityEventLogger $securityEvents): JsonResponse
    {
        $membership = TenantMembership::query()->with('tenant')
            ->where('idUser', $request->user()->id)
            ->where('idTenant', $tenantId)
            ->where('state', TenantMembershipState::Active)
            ->first();

        if ($membership === null) {
            return $this->notAvailable();
        }

        if (! $contexts->isOwner($membership)) {
            return response()->json(['error' => ['code' => 'TENANT_OWNER_REQUIRED', 'message' => 'Ação não permitida para este tenant.']], 403);
        }

        $state = TenantState::from($request->string('state')->toString());

        if ($membership->tenant->state === TenantState::Provisioning || $membership->tenant->state === TenantState::Failed) {
            return response()->json(['error' => ['code' => 'TENANT_STATE_CONFLICT', 'message' => 'O tenant não pode ser alterado neste estado.']], 409);
        }

        $membership->tenant->update(['state' => $state]);
        $securityEvents->record(TenantSecurityEvent::AvailabilityChanged, $request->user()->id);

        return response()->json(['tenant' => $this->tenant($membership->tenant->refresh())]);
    }

    private function notAvailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'TENANT_NOT_AVAILABLE', 'message' => 'O tenant não está disponível.']], 404);
    }

    /** @return array{id: string, displayName: string, state: string, selectable: bool} */
    private function tenant(Tenant $tenant): array
    {
        return ['id' => $tenant->id, 'displayName' => $tenant->displayName, 'state' => $tenant->state->value, 'selectable' => $tenant->state === TenantState::Active];
    }
}
