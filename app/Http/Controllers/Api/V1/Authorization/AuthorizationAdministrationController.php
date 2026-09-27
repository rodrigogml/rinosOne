<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Domain\Authorization\Administration\AuthorizationAdministrationAccessDeniedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\AuthorizationAuditIndexRequest;
use App\Http\Requests\Authorization\AuthorizationDisplayNameRequest;
use App\Http\Requests\Authorization\AuthorizationRoleIdRequest;
use App\Http\Requests\Authorization\AuthorizationSubjectRequest;
use App\Http\Requests\Authorization\CreateAdministrativeRoleRequest;
use App\Http\Requests\Authorization\CreateAuthorizationRestrictionRequest;
use App\Http\Requests\Authorization\ExplainAuthorizationDecisionRequest;
use App\Http\Requests\Authorization\GrantAdministrativePermissionRequest;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\AuthorizationRole;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationFacade;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use LogicException;

class AuthorizationAdministrationController extends Controller
{
    public function storeRole(string $tenantId, CreateAdministrativeRoleRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $role = $administration->createTenantRole($request->user(), (int) $tenantId, $request->string('key')->toString(), $request->string('displayName')->toString(), $request->string('description')->toString());
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException $exception) {
            return $this->conflict($exception);
        }

        return response()->json(['role' => $this->role($role)], 201);
    }

    public function grantPermission(string $tenantId, string $roleId, GrantAdministrativePermissionRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, $request->user(), (int) $tenantId);
            $administration->grantPermission($request->user(), (int) $tenantId, $this->roleFor($roleId, (int) $tenantId), AuthorizationPermission::query()->findOrFail($request->integer('permissionId')));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function assignRole(string $tenantId, string $roleId, AuthorizationSubjectRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $this->assertCanManage($administration, $request->user(), (int) $tenantId);
            $assignment = $administration->assignRole($request->user(), (int) $tenantId, $this->roleFor($roleId, (int) $tenantId), User::query()->findOrFail($request->integer('userId')));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->json(['assignment' => ['id' => $assignment->id, 'roleId' => $assignment->idRole, 'userId' => $assignment->idUser, 'tenantId' => $assignment->idTenant, 'state' => $assignment->state]], 201);
    }

    public function removeRoleAssignment(string $tenantId, string $roleId, string $userId, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, request()->user(), (int) $tenantId);
            $administration->removeRoleAssignment(request()->user(), (int) $tenantId, $this->roleFor($roleId, (int) $tenantId), User::query()->findOrFail($userId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException $exception) {
            return $this->conflict($exception);
        } catch (ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function storeGroup(string $tenantId, AuthorizationDisplayNameRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $group = $administration->createTenantGroup($request->user(), (int) $tenantId, $request->string('displayName')->toString());
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException $exception) {
            return $this->conflict($exception);
        }

        return response()->json(['group' => ['id' => $group->id, 'displayName' => $group->displayName]], 201);
    }

    public function addGroupMember(string $tenantId, string $groupId, AuthorizationSubjectRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, $request->user(), (int) $tenantId);
            $administration->addGroupMember($request->user(), (int) $tenantId, $this->groupFor($groupId, (int) $tenantId), User::query()->findOrFail($request->integer('userId')));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function removeGroupMember(string $tenantId, string $groupId, string $userId, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, request()->user(), (int) $tenantId);
            $administration->removeGroupMember(request()->user(), (int) $tenantId, $this->groupFor($groupId, (int) $tenantId), User::query()->findOrFail($userId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function grantGroupRole(string $tenantId, string $groupId, AuthorizationRoleIdRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, $request->user(), (int) $tenantId);
            $administration->grantRoleToGroup($request->user(), (int) $tenantId, $this->roleFor((string) $request->integer('roleId'), (int) $tenantId), $this->groupFor($groupId, (int) $tenantId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function removeGroupRole(string $tenantId, string $groupId, string $roleId, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, request()->user(), (int) $tenantId);
            $administration->removeRoleFromGroup(request()->user(), (int) $tenantId, $this->roleFor($roleId, (int) $tenantId), $this->groupFor($groupId, (int) $tenantId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function storeRestriction(string $tenantId, CreateAuthorizationRestrictionRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $this->assertCanManage($administration, $request->user(), (int) $tenantId);
            $restriction = $administration->createRestriction($request->user(), (int) $tenantId, AuthorizationPermission::query()->findOrFail($request->integer('permissionId')), $request->filled('userId') ? User::query()->findOrFail($request->integer('userId')) : null, $request->filled('groupId') ? $this->groupFor((string) $request->integer('groupId'), (int) $tenantId) : null, $request->date('startsAt'), $request->date('endsAt'));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException $exception) {
            return $this->conflict($exception);
        } catch (ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->json(['restriction' => ['id' => $restriction->id, 'permissionId' => $restriction->idPermission, 'userId' => $restriction->idUser, 'groupId' => $restriction->idGroup, 'active' => $restriction->active]], 201);
    }

    public function deactivateRestriction(string $tenantId, string $restrictionId, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, request()->user(), (int) $tenantId);
            $administration->deactivateRestriction(request()->user(), (int) $tenantId, AuthorizationRestriction::query()->findOrFail($restrictionId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function removeMembership(string $tenantId, string $membershipId, AuthorizationAdministrationFacade $administration): JsonResponse|Response
    {
        try {
            $this->assertCanManage($administration, request()->user(), (int) $tenantId);
            $administration->removeMembership(request()->user(), (int) $tenantId, TenantMembership::query()->findOrFail($membershipId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException $exception) {
            return $this->conflict($exception);
        } catch (ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->noContent();
    }

    public function effectiveAccess(string $tenantId, string $userId, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $this->assertCanRead($administration, request()->user(), (int) $tenantId);
            $access = $administration->effectiveAccess(request()->user(), (int) $tenantId, User::query()->findOrFail($userId));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->json(['effectiveAccess' => $access]);
    }

    public function explain(string $tenantId, string $userId, ExplainAuthorizationDecisionRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $this->assertCanRead($administration, $request->user(), (int) $tenantId);
            $explanation = $administration->explain($request->user(), (int) $tenantId, User::query()->findOrFail($userId), $request->string('permissionKey')->toString());
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        } catch (LogicException|ModelNotFoundException) {
            return $this->notAvailable();
        }

        return response()->json(['explanation' => $explanation]);
    }

    public function auditEvents(string $tenantId, AuthorizationAuditIndexRequest $request, AuthorizationAdministrationFacade $administration): JsonResponse
    {
        try {
            $this->assertCanRead($administration, $request->user(), (int) $tenantId);
            $events = $administration->auditEvents($request->user(), (int) $tenantId, $request->string('operation')->toString() ?: null, $request->string('targetType')->toString() ?: null, $request->integer('targetId') ?: null, $request->integer('perPage', 25));
        } catch (AuthorizationAdministrationAccessDeniedException) {
            return $this->denied();
        }

        return response()->json(['events' => $events->getCollection()->map(fn ($event): array => ['id' => $event->id, 'occurredAt' => $event->occurredAt->toIso8601String(), 'operation' => $event->operation, 'targetType' => $event->targetType, 'targetId' => $event->targetId, 'actorUserId' => $event->idActorUser])->values(), 'pagination' => ['page' => $events->currentPage(), 'perPage' => $events->perPage(), 'total' => $events->total()]]);
    }

    private function roleFor(string $roleId, int $tenantId): AuthorizationRole
    {
        return AuthorizationRole::query()->whereKey($roleId)->where('scope', 'TENANT')->where(fn ($query) => $query->where('idTenant', $tenantId)->orWhereNull('idTenant'))->firstOrFail();
    }

    private function groupFor(string $groupId, int $tenantId): AuthorizationGroup
    {
        return AuthorizationGroup::query()->whereKey($groupId)->where('scope', 'TENANT')->where('idTenant', $tenantId)->firstOrFail();
    }

    private function role(AuthorizationRole $role): array
    {
        return ['id' => $role->id, 'key' => $role->key, 'displayName' => $role->displayName, 'description' => $role->description];
    }

    private function denied(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida para este tenant.']], 403);
    }

    private function notAvailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE', 'message' => 'Recurso de autorização não disponível.']], 404);
    }

    private function conflict(LogicException $exception): JsonResponse
    {
        return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_CONFLICT', 'message' => 'A operação não pôde ser concluída no estado atual.']], 409);
    }

    private function assertCanManage(AuthorizationAdministrationFacade $administration, User $actor, int $tenantId): void
    {
        $administration->assertCanManage($actor, $tenantId);
    }

    private function assertCanRead(AuthorizationAdministrationFacade $administration, User $actor, int $tenantId): void
    {
        $administration->assertCanRead($actor, $tenantId);
    }
}
