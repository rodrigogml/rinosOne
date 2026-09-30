<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\ResourceShareRequest;
use App\Http\Requests\Authorization\UpdateResourceShareRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use App\Services\Authorization\Administration\AuthorizationAdministrationContextResolver;
use App\Services\Authorization\Administration\AuthorizationAdministrationProjectionSerializer;
use App\Services\Authorization\Administration\ContextualAuthorizationResourceShareService;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

/** Exposes route-bound workspace-folder sharing without introducing item ownership. */
class ContextualAuthorizationResourceShareController extends Controller
{
    public function __construct(private readonly PolicyVersionService $policyVersions) {}

    public function personalIndex(string $resourceType, string $resourceId, Request $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        return $this->index($request->user(), $this->personal($request->user(), $contexts), $resourceType, (int) $resourceId, $shares, $serializer);
    }

    public function tenantIndex(string $tenantId, string $resourceType, string $resourceId, Request $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        return $this->index($request->user(), $this->tenant($request->user(), (int) $tenantId, $contexts), $resourceType, (int) $resourceId, $shares, $serializer);
    }

    public function personalStore(string $resourceType, string $resourceId, ResourceShareRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        return $this->store($request->user(), $this->personal($request->user(), $contexts), $resourceType, (int) $resourceId, $request, $shares, $serializer);
    }

    public function tenantStore(string $tenantId, string $resourceType, string $resourceId, ResourceShareRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        return $this->store($request->user(), $this->tenant($request->user(), (int) $tenantId, $contexts), $resourceType, (int) $resourceId, $request, $shares, $serializer);
    }

    public function personalUpdate(string $resourceType, string $resourceId, string $shareId, UpdateResourceShareRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        return $this->update($request->user(), $this->personal($request->user(), $contexts), $resourceType, (int) $resourceId, (int) $shareId, $request, $shares, $serializer);
    }

    public function tenantUpdate(string $tenantId, string $resourceType, string $resourceId, string $shareId, UpdateResourceShareRequest $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        return $this->update($request->user(), $this->tenant($request->user(), (int) $tenantId, $contexts), $resourceType, (int) $resourceId, (int) $shareId, $request, $shares, $serializer);
    }

    public function personalDestroy(string $resourceType, string $resourceId, string $shareId, Request $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares): JsonResponse
    {
        return $this->destroy($request->user(), $this->personal($request->user(), $contexts), $resourceType, (int) $resourceId, (int) $shareId, $shares);
    }

    public function tenantDestroy(string $tenantId, string $resourceType, string $resourceId, string $shareId, Request $request, AuthorizationAdministrationContextResolver $contexts, ContextualAuthorizationResourceShareService $shares): JsonResponse
    {
        return $this->destroy($request->user(), $this->tenant($request->user(), (int) $tenantId, $contexts), $resourceType, (int) $resourceId, (int) $shareId, $shares);
    }

    private function index(User $actor, AuthorizationAdministrationContext $context, string $resourceType, int $resourceId, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        if (($error = $this->shareError($context, $resourceType)) !== null) {
            return $error;
        }
        try {
            $items = $shares->shares($context, $resourceId);
        } catch (LogicException) {
            return $this->notAvailable();
        }

        return response()->json(['resource' => ['resourceType' => 'FOLDER', 'resourceId' => $resourceId], 'workspaceResponsible' => $this->workspaceResponsible($actor, $context), 'shares' => $serializer->shares($items)]);
    }

    private function store(User $actor, AuthorizationAdministrationContext $context, string $resourceType, int $resourceId, ResourceShareRequest $request, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        if (($error = $this->shareError($context, $resourceType)) !== null) {
            return $error;
        }
        if (($stale = $this->stale($request, $context)) !== null) {
            return $stale;
        }
        try {
            $share = $shares->create($actor, $context, $resourceId, $request->integer('subjectId'), $request->string('relation')->toString());
        } catch (LogicException) {
            return $this->notAvailable();
        }

        return response()->json(['share' => $serializer->shares([$share])[0], 'contextVersion' => $this->version($context)], 201);
    }

    private function update(User $actor, AuthorizationAdministrationContext $context, string $resourceType, int $resourceId, int $shareId, UpdateResourceShareRequest $request, ContextualAuthorizationResourceShareService $shares, AuthorizationAdministrationProjectionSerializer $serializer): JsonResponse
    {
        if (($error = $this->shareError($context, $resourceType)) !== null) {
            return $error;
        }
        if (($stale = $this->stale($request, $context)) !== null) {
            return $stale;
        }
        try {
            $share = $shares->update($actor, $context, $resourceId, $shareId, $request->string('relation')->toString());
        } catch (LogicException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_RESOURCE_SHARE_NOT_DIRECT', 'message' => 'Somente compartilhamentos diretos podem ser alterados neste recurso.']], 409);
        }

        return response()->json(['share' => $serializer->shares([$share])[0], 'contextVersion' => $this->version($context)]);
    }

    private function destroy(User $actor, AuthorizationAdministrationContext $context, string $resourceType, int $resourceId, int $shareId, ContextualAuthorizationResourceShareService $shares): JsonResponse
    {
        if (($error = $this->shareError($context, $resourceType)) !== null) {
            return $error;
        }
        try {
            $shares->revoke($actor, $context, $resourceId, $shareId);
        } catch (LogicException) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_RESOURCE_SHARE_NOT_DIRECT', 'message' => 'Somente compartilhamentos diretos podem ser revogados neste recurso.']], 409);
        }

        return response()->json(null, 204);
    }

    private function shareError(AuthorizationAdministrationContext $context, string $resourceType): ?JsonResponse
    {
        if (! $context->capabilities->canManageSharing) {
            return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_DENIED', 'message' => 'Compartilhamento não permitido neste contexto.']], 403);
        }
        if ($resourceType !== 'FOLDER' || ($context->scope === AuthorizationScope::Tenant && ! Tenant::query()->whereKey($context->tenantId)->exists())) {
            return $this->notAvailable();
        }

        return null;
    }

    private function personal(User $actor, AuthorizationAdministrationContextResolver $contexts): AuthorizationAdministrationContext
    {
        return $contexts->forPersonal($actor);
    }

    private function tenant(User $actor, int $tenantId, AuthorizationAdministrationContextResolver $contexts): AuthorizationAdministrationContext
    {
        return $contexts->forTenant($actor, $tenantId);
    }

    private function workspaceResponsible(User $actor, AuthorizationAdministrationContext $context): array
    {
        return $context->scope === AuthorizationScope::Personal
            ? ['type' => 'USER', 'id' => $actor->id, 'displayName' => $actor->displayName]
            : ['type' => 'TENANT', 'id' => $context->tenantId, 'displayName' => Tenant::query()->whereKey($context->tenantId)->value('displayName')];
    }

    private function stale(Request $request, AuthorizationAdministrationContext $context): ?JsonResponse
    {
        return ! $request->filled('expectedContextVersion') || hash_equals($this->version($context), $request->string('expectedContextVersion')->toString())
            ? null
            : response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_CONTEXT_STALE', 'message' => 'O contexto de autorização foi alterado. Atualize os dados e tente novamente.']], 412);
    }

    private function version(AuthorizationAdministrationContext $context): string
    {
        return (string) $this->policyVersions->current($context->scope, $context->tenantId);
    }

    private function notAvailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE', 'message' => 'Recurso de compartilhamento não disponível.']], 404);
    }
}
