<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Domain\Authorization\Administration\AuthorizationAdministrationSubjectNotAvailableException;
use App\Domain\Authorization\AuthorizationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\ContextualAuthorizationQueryRequest;
use App\Http\Requests\Authorization\ContextualExplainAuthorizationDecisionRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use App\Services\Authorization\Administration\AuthorizationAdministrationContextResolver;
use App\Services\Authorization\Administration\AuthorizationAdministrationProjectionSerializer;
use App\Services\Authorization\Administration\ContextualAuthorizationAdministrationQuery;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationContextDto;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Exposes route-bound read projections for the contextual authorization administration surface.
 *
 * No request body, query parameter, or client state chooses a workspace scope. The route and the
 * authenticated actor are resolved independently for every request.
 */
class ContextualAuthorizationAdministrationController extends Controller
{
    public function __construct(private readonly PolicyVersionService $policyVersions) {}

    public function tenantContext(string $tenantId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->contextResponse($this->tenant($request->user(), (int) $tenantId, $contexts), $serializer);
    }

    public function personalContext(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->contextResponse($this->personal($request->user(), $contexts), $serializer);
    }

    public function platformContext(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->contextResponse($this->platform($request->user(), $contexts), $serializer);
    }

    public function tenantSubjects(string $tenantId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->subjectsResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $administration, $serializer);
    }

    public function personalSubjects(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->subjectsResponse($request, $this->personal($request->user(), $contexts), $administration, $serializer);
    }

    public function platformSubjects(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->subjectsResponse($request, $this->platform($request->user(), $contexts), $administration, $serializer);
    }

    public function tenantRoles(string $tenantId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->rolesResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $administration, $serializer);
    }

    public function personalRoles(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->rolesResponse($request, $this->personal($request->user(), $contexts), $administration, $serializer);
    }

    public function platformRoles(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->rolesResponse($request, $this->platform($request->user(), $contexts), $administration, $serializer);
    }

    public function tenantGroups(string $tenantId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->groupsResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $administration, $serializer);
    }

    public function personalGroups(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->groupsResponse($request, $this->personal($request->user(), $contexts), $administration, $serializer);
    }

    public function platformGroups(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->groupsResponse($request, $this->platform($request->user(), $contexts), $administration, $serializer);
    }

    public function tenantPermissions(string $tenantId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->permissionsResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $administration, $serializer);
    }

    public function personalPermissions(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->permissionsResponse($request, $this->personal($request->user(), $contexts), $administration, $serializer);
    }

    public function platformPermissions(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->permissionsResponse($request, $this->platform($request->user(), $contexts), $administration, $serializer);
    }

    public function tenantEffectiveAccess(string $tenantId, string $subjectType, string $subjectId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->effectiveAccessResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $subjectType, (int) $subjectId, $administration, $serializer);
    }

    public function personalEffectiveAccess(string $subjectType, string $subjectId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->effectiveAccessResponse($request, $this->personal($request->user(), $contexts), $subjectType, (int) $subjectId, $administration, $serializer);
    }

    public function platformEffectiveAccess(string $subjectType, string $subjectId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->effectiveAccessResponse($request, $this->platform($request->user(), $contexts), $subjectType, (int) $subjectId, $administration, $serializer);
    }

    public function tenantExplain(string $tenantId, string $subjectType, string $subjectId, ContextualExplainAuthorizationDecisionRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->explainResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $subjectType, (int) $subjectId, $administration, $serializer);
    }

    public function personalExplain(string $subjectType, string $subjectId, ContextualExplainAuthorizationDecisionRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->explainResponse($request, $this->personal($request->user(), $contexts), $subjectType, (int) $subjectId, $administration, $serializer);
    }

    public function platformExplain(string $subjectType, string $subjectId, ContextualExplainAuthorizationDecisionRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->explainResponse($request, $this->platform($request->user(), $contexts), $subjectType, (int) $subjectId, $administration, $serializer);
    }

    public function tenantAuditEvents(string $tenantId, ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->auditEventsResponse($request, $this->tenant($request->user(), (int) $tenantId, $contexts), $administration, $serializer);
    }

    public function personalAuditEvents(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->auditEventsResponse($request, $this->personal($request->user(), $contexts), $administration, $serializer);
    }

    public function platformAuditEvents(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        return $this->auditEventsResponse($request, $this->platform($request->user(), $contexts), $administration, $serializer);
    }

    private function subjectsResponse(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContext $context, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        $subjects = $administration->subjects($request->user(), $context, $request->string('query')->toString() ?: null, $request->integer('page', 1), $request->integer('perPage', 25));

        return response()->json(['subjects' => $serializer->subjects($subjects->items()), 'pagination' => $this->pagination($subjects)]);
    }

    private function rolesResponse(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContext $context, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        $roles = $administration->roles($context, $request->string('query')->toString() ?: null, $request->integer('page', 1), $request->integer('perPage', 25));

        return response()->json(['roles' => $serializer->catalog($roles->items()), 'pagination' => $this->pagination($roles)]);
    }

    private function permissionsResponse(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContext $context, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }

        return response()->json(['permissions' => $serializer->catalog($administration->permissions($context, $request->string('query')->toString() ?: null))]);
    }

    private function groupsResponse(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContext $context, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        $groups = $administration->groups($context, $request->string('query')->toString() ?: null, $request->integer('page', 1), $request->integer('perPage', 25));

        return response()->json(['groups' => $serializer->catalog($groups->items()), 'pagination' => $this->pagination($groups)]);
    }

    private function effectiveAccessResponse(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContext $context, string $subjectType, int $subjectId, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        try {
            $access = $administration->effectiveAccess($request->user(), $context, $subjectType, $subjectId);
        } catch (AuthorizationAdministrationSubjectNotAvailableException) {
            return $this->notAvailable();
        }

        return response()->json(['effectiveAccess' => ['subject' => $serializer->subjects([$access['subject']])[0], 'effectiveCapabilities' => $access['effectiveCapabilities'], 'factors' => $access['factors']]]);
    }

    private function explainResponse(ContextualExplainAuthorizationDecisionRequest $request, AuthorizationAdministrationContext $context, string $subjectType, int $subjectId, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        try {
            $explanation = $administration->explain($request->user(), $context, $subjectType, $subjectId, $request->string('permissionKey')->toString());
        } catch (AuthorizationAdministrationSubjectNotAvailableException) {
            return $this->notAvailable();
        }

        return response()->json(['explanation' => ['subject' => $serializer->subjects([$explanation['subject']])[0], 'allowed' => $explanation['allowed'], 'reasonCode' => $explanation['reasonCode']]]);
    }

    private function auditEventsResponse(ContextualAuthorizationQueryRequest $request, AuthorizationAdministrationContext $context, ContextualAuthorizationAdministrationQuery $administration, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        try {
            $events = $administration->auditEvents($request->user(), $context, $request->string('operation')->toString() ?: null, $request->string('targetType')->toString() ?: null, $request->integer('targetId') ?: null, $request->date('occurredAfter'), $request->date('occurredBefore'), $request->integer('page', 1), $request->integer('perPage', 25));
        } catch (InvalidArgumentException) {
            return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Os dados informados não são válidos.']], 422);
        }

        return response()->json(['events' => $serializer->auditEvents($events->items()), 'pagination' => $this->pagination($events)]);
    }

    private function contextResponse(AuthorizationAdministrationContext $context, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse|Response
    {
        if (($denied = $this->deniedUnlessReadable($context)) !== null) {
            return $denied;
        }
        [$displayName, $workspaceKind] = match ($context->scope) {
            AuthorizationScope::Tenant => [$this->tenantDisplayName($context->tenantId), 'TENANT'],
            AuthorizationScope::Personal => ['Espaço pessoal', 'PERSONAL'],
            AuthorizationScope::Platform => ['Plataforma', null],
        };

        if ($displayName === null) {
            return $this->notAvailable();
        }

        return response()->json([...$serializer->context(AuthorizationAdministrationContextDto::fromContext($context, $displayName, $workspaceKind)), 'contextVersion' => (string) $this->policyVersions->current($context->scope, $context->tenantId), 'sections' => $this->sections($context)]);
    }

    private function tenant(User $actor, int $tenantId, AuthorizationAdministrationContextResolver $contexts): AuthorizationAdministrationContext
    {
        return $contexts->forTenant($actor, $tenantId);
    }

    private function personal(User $actor, AuthorizationAdministrationContextResolver $contexts): AuthorizationAdministrationContext
    {
        return $contexts->forPersonal($actor);
    }

    private function platform(User $actor, AuthorizationAdministrationContextResolver $contexts): AuthorizationAdministrationContext
    {
        return $contexts->forPlatform($actor);
    }

    private function deniedUnlessReadable(AuthorizationAdministrationContext $context): ?JsonResponse
    {
        if (! $context->capabilities->canReadAccess) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Ação não permitida neste contexto.']], 403);
        }

        if ($context->scope === AuthorizationScope::Tenant && ! Tenant::query()->whereKey($context->tenantId)->exists()) {
            return $this->notAvailable();
        }

        return null;
    }

    private function notAvailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE', 'message' => 'Recurso de autorização não disponível.']], 404);
    }

    /** @return array{page: int, perPage: int, total: int, lastPage: int} */
    private function pagination(LengthAwarePaginator $results): array
    {
        return ['page' => $results->currentPage(), 'perPage' => $results->perPage(), 'total' => $results->total(), 'lastPage' => $results->lastPage()];
    }

    /** @return list<string> */
    private function sections(AuthorizationAdministrationContext $context): array
    {
        $sections = ['subjects', 'roles', 'groups', 'permissions', 'audit'];
        if ($context->capabilities->canManageSharing) {
            $sections[] = 'sharing';
        }
        if ($context->capabilities->canUseAdvancedControls) {
            $sections[] = 'advanced';
        }

        return $sections;
    }

    private function tenantDisplayName(?int $tenantId): ?string
    {
        return $tenantId === null ? null : Tenant::query()->whereKey($tenantId)->value('displayName');
    }
}
