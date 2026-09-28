<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Domain\Authorization\Administration\AuthorizationAdministrationAccessDeniedException;
use App\Domain\Authorization\AuthorizationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\CreateAuthorizationDelegationRequest;
use App\Http\Requests\Authorization\CreateAuthorizationSeparationRuleRequest;
use App\Http\Requests\Authorization\CreateAuthorizationServiceIdentityRequest;
use App\Http\Requests\Authorization\CreateTemporaryAuthorizationAccessRequest;
use App\Http\Requests\Authorization\GrantAdministrativePermissionRequest;
use App\Http\Requests\Authorization\IssueAuthorizationServiceCredentialRequest;
use App\Http\Requests\Authorization\PublishAdvancedAuthorizationPolicyRequest;
use App\Models\AuthorizationAccessRequest;
use App\Models\AuthorizationDelegation;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationPolicy;
use App\Models\AuthorizationServiceCredential;
use App\Models\AuthorizationServiceIdentity;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationFacade;
use App\Services\Authorization\Advanced\AuthorizationAccessRequestService;
use App\Services\Authorization\Advanced\AuthorizationDelegationService;
use App\Services\Authorization\Advanced\AuthorizationPolicyService;
use App\Services\Authorization\Advanced\AuthorizationSeparationRuleService;
use App\Services\Authorization\Advanced\AuthorizationServiceIdentityService;
use App\Services\Authorization\Advanced\ServiceIdentityAuthorizationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

/** Exposes tenant-scoped advanced authorization actions to authorized human administrators. */
class AdvancedAuthorizationAdministrationController extends Controller
{
    public function accessRequests(string $tenantId, Request $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        $query = AuthorizationAccessRequest::query()->where('idTenant', $tenantId)->orderByDesc('createdAt');
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            if (! TenantMembership::query()->where('idTenant', $tenantId)->where('idUser', $request->user()->id)->where('state', 'ACTIVE')->exists()) {
                return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'As solicitações não estão disponíveis.']], 404);
            }
            $query->where('idRequesterUser', $request->user()->id);
        }

        return response()->json(['accessRequests' => $query->limit(100)->get()->map(fn (AuthorizationAccessRequest $accessRequest): array => $this->accessRequest($accessRequest))->values()]);
    }

    public function publishPolicy(string $tenantId, PublishAdvancedAuthorizationPolicyRequest $request, AuthorizationAdministrationFacade $administration, AuthorizationPolicyService $policies): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $policy = $policies->publish($data['key'], AuthorizationScope::Tenant, (int) $tenantId, $data['definition'], $request->user()->id, $request->header('X-Correlation-Id'));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A política não está disponível.']], 422);
        }

        return response()->json(['policy' => ['id' => $policy->id, 'key' => $policy->key, 'version' => $policy->version, 'active' => $policy->active]], 201);
    }

    public function bindPolicy(string $tenantId, string $policyId, GrantAdministrativePermissionRequest $request, AuthorizationAdministrationFacade $administration, AuthorizationPolicyService $policies): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $policy = AuthorizationPolicy::query()->whereKey($policyId)->where('idTenant', $tenantId)->firstOrFail();
            $binding = $policies->bind($policy, AuthorizationPermission::query()->whereKey($data['permissionId'])->where('scope', AuthorizationScope::Tenant->value)->firstOrFail(), $request->user()->id, $request->header('X-Correlation-Id'));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A política não está disponível.']], 422);
        }

        return response()->json(['policyBinding' => ['id' => $binding->id, 'policyId' => $binding->idPolicy, 'permissionId' => $binding->idPermission]], 201);
    }

    public function createSeparationRule(string $tenantId, CreateAuthorizationSeparationRuleRequest $request, AuthorizationAdministrationFacade $administration, AuthorizationSeparationRuleService $rules): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $rule = $rules->create(AuthorizationPermission::query()->findOrFail($data['permissionId']), AuthorizationPermission::query()->findOrFail($data['incompatiblePermissionId']), AuthorizationScope::Tenant, (int) $tenantId, $request->user()->id);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A regra de separação não está disponível.']], 422);
        }

        return response()->json(['separationRule' => ['id' => $rule->id, 'permissionId' => $rule->idPermission, 'incompatiblePermissionId' => $rule->idIncompatiblePermission, 'active' => $rule->active]], 201);
    }

    public function createDelegation(string $tenantId, CreateAuthorizationDelegationRequest $request, AuthorizationAdministrationFacade $administration, AuthorizationDelegationService $delegations): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $delegation = $delegations->create(User::query()->findOrFail($data['delegatorUserId']), User::query()->findOrFail($data['recipientUserId']), AuthorizationPermission::query()->whereKey($data['permissionId'])->where('scope', AuthorizationScope::Tenant->value)->firstOrFail(), AuthorizationScope::Tenant, (int) $tenantId, 'ROLE_ASSIGNMENT', $data['originAssignmentId'], now()->parse($data['startsAt']), now()->parse($data['endsAt']), $data['limits'] ?? null, $request->user()->id);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A delegação não está disponível.']], 422);
        }

        return response()->json(['delegation' => $this->delegation($delegation)], 201);
    }

    public function revokeDelegation(string $tenantId, string $delegationId, Request $request, AuthorizationAdministrationFacade $administration, AuthorizationDelegationService $delegations): JsonResponse
    {
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $delegation = AuthorizationDelegation::query()->whereKey($delegationId)->where('idTenant', $tenantId)->firstOrFail();
            $delegations->revoke($delegation, $request->user()->id);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A delegação não está disponível.']], 404);
        }

        return response()->json(['delegation' => $this->delegation($delegation->fresh())]);
    }

    public function requestAccess(string $tenantId, CreateTemporaryAuthorizationAccessRequest $request, AuthorizationAccessRequestService $requests): JsonResponse
    {
        $data = $request->validated();
        try {
            $accessRequest = $requests->request($request->user(), $request->user(), AuthorizationPermission::query()->findOrFail($data['permissionId']), AuthorizationScope::Tenant, (int) $tenantId, now()->parse($data['startsAt']), now()->parse($data['endsAt']));
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A solicitação não pôde ser criada.']], 422);
        }

        return response()->json(['accessRequest' => $this->accessRequest($accessRequest)], 201);
    }

    public function approveAccess(string $tenantId, string $requestId, Request $request, AuthorizationAdministrationFacade $administration, AuthorizationAccessRequestService $requests): JsonResponse
    {
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $accessRequest = AuthorizationAccessRequest::query()->whereKey($requestId)->where('idTenant', $tenantId)->firstOrFail();
            $requests->approve($accessRequest, $request->user());
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A solicitação não está disponível.']], 409);
        }

        return response()->json(['accessRequest' => $this->accessRequest($accessRequest->fresh())]);
    }

    public function revokeAccess(string $tenantId, string $requestId, Request $request, AuthorizationAccessRequestService $requests): JsonResponse
    {
        try {
            $accessRequest = AuthorizationAccessRequest::query()->whereKey($requestId)->where('idTenant', $tenantId)->firstOrFail();
            $requests->revoke($accessRequest, $request->user());
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A solicitação não está disponível.']], 409);
        }

        return response()->json(['accessRequest' => $this->accessRequest($accessRequest->fresh())]);
    }

    public function createServiceIdentity(string $tenantId, CreateAuthorizationServiceIdentityRequest $request, AuthorizationAdministrationFacade $administration, AuthorizationServiceIdentityService $identities): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $identity = $identities->create(User::query()->findOrFail($data['ownerUserId']), $data['displayName'], $data['purpose'], AuthorizationScope::Tenant, (int) $tenantId, isset($data['startsAt']) ? now()->parse($data['startsAt']) : null, isset($data['endsAt']) ? now()->parse($data['endsAt']) : null);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A identidade técnica não está disponível.']], 422);
        }

        return response()->json(['serviceIdentity' => $this->identity($identity)], 201);
    }

    public function issueCredential(string $tenantId, string $identityId, IssueAuthorizationServiceCredentialRequest $request, AuthorizationAdministrationFacade $administration, AuthorizationServiceIdentityService $identities): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $identity = AuthorizationServiceIdentity::query()->whereKey($identityId)->where('idTenant', $tenantId)->firstOrFail();
            $issued = $identities->issueCredential($identity, $data['displayName'], $data['permissionKeys'] ?? null, isset($data['expiresAt']) ? now()->parse($data['expiresAt']) : null);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A chave técnica não está disponível.']], 422);
        }

        return response()->json(['credentialId' => $issued->credentialId, 'apiKey' => $issued->apiKey], 201);
    }

    public function grantServiceIdentityPermission(string $tenantId, string $identityId, GrantAdministrativePermissionRequest $request, AuthorizationAdministrationFacade $administration, ServiceIdentityAuthorizationService $authorization): JsonResponse
    {
        $data = $request->validated();
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $identity = AuthorizationServiceIdentity::query()->whereKey($identityId)->where('idTenant', $tenantId)->firstOrFail();
            $permission = AuthorizationPermission::query()->whereKey($data['permissionId'])->where('scope', 'TENANT')->firstOrFail();
            $authorization->grant($identity, $permission);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (LogicException|ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A permissão técnica não está disponível.']], 422);
        }

        return response()->json([], 204);
    }

    public function revokeCredential(string $tenantId, string $credentialId, Request $request, AuthorizationAdministrationFacade $administration, AuthorizationServiceIdentityService $identities): JsonResponse
    {
        try {
            $administration->assertCanManage($request->user(), (int) $tenantId);
            $credential = AuthorizationServiceCredential::query()->whereKey($credentialId)->whereHas('identity', fn ($query) => $query->where('idTenant', $tenantId))->firstOrFail();
            $identities->revokeCredential($credential);
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'ADVANCED_AUTHORIZATION_NOT_AVAILABLE', 'message' => 'A chave técnica não está disponível.']], 404);
        }

        return response()->json([], 204);
    }

    private function accessRequest(AuthorizationAccessRequest $request): array
    {
        return ['id' => $request->id, 'requesterUserId' => $request->idRequesterUser, 'recipientUserId' => $request->idRecipientUser, 'permissionId' => $request->idPermission, 'state' => $request->state, 'startsAt' => $request->startsAt->toIso8601String(), 'endsAt' => $request->endsAt->toIso8601String()];
    }

    private function delegation(AuthorizationDelegation $delegation): array
    {
        return ['id' => $delegation->id, 'delegatorUserId' => $delegation->idDelegatorUser, 'recipientUserId' => $delegation->idRecipientUser, 'permissionId' => $delegation->idPermission, 'state' => $delegation->state, 'startsAt' => $delegation->startsAt->toIso8601String(), 'endsAt' => $delegation->endsAt->toIso8601String()];
    }

    private function identity(AuthorizationServiceIdentity $identity): array
    {
        return ['id' => $identity->id, 'displayName' => $identity->displayName, 'purpose' => $identity->purpose, 'state' => $identity->state];
    }
}
