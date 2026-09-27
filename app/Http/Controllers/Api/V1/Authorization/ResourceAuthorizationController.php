<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\CheckResourceAuthorizationBatchRequest;
use App\Models\AuthorizationPermission;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizedPersonalWorkspaceFolderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Exposes safe, resource-aware authorization decisions to authenticated application surfaces.
 */
class ResourceAuthorizationController extends Controller
{
    public function personalWorkspaceFolders(Request $request, AuthorizedPersonalWorkspaceFolderQuery $folders): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('perPage', 50)));
        $page = max(1, $request->integer('page', 1));
        $items = $folders->pageFor($request->user(), $page, $perPage);

        return response()->json([
            'folders' => $items->map(fn ($folder): array => [
                'id' => $folder->id,
                'parentFolderId' => $folder->idParentFolder,
                'displayName' => $folder->displayName,
            ])->values(),
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function checkBatch(CheckResourceAuthorizationBatchRequest $request, AuthorizationService $authorization): JsonResponse
    {
        $permissions = AuthorizationPermission::query()
            ->whereIn('key', collect($request->validated('checks'))->pluck('permissionKey')->unique())
            ->where('active', true)
            ->get()
            ->keyBy('key');

        $decisions = collect($request->validated('checks'))->map(function (array $check) use ($permissions, $request, $authorization): array {
            $permission = $permissions->get($check['permissionKey']);
            if ($permission === null) {
                return ['allowed' => false, 'reasonCode' => 'PERMISSION_NOT_AVAILABLE'];
            }

            $scope = AuthorizationScope::tryFrom($permission->scope);
            if ($scope === null) {
                return ['allowed' => false, 'reasonCode' => 'PERMISSION_NOT_AVAILABLE'];
            }

            try {
                $resource = new ResourceReference(
                    $check['resource']['type'],
                    $check['resource']['id'],
                    $scope,
                    $check['tenantId'] ?? null,
                );
            } catch (InvalidArgumentException) {
                return ['allowed' => false, 'reasonCode' => 'RESOURCE_CONTEXT_INVALID'];
            }

            $decision = $authorization->check($request->user(), $check['permissionKey'], $scope, $check['tenantId'] ?? null, $resource);

            return ['allowed' => $decision->allowed, 'reasonCode' => $decision->reasonCode];
        })->values();

        return response()->json(['decisions' => $decisions]);
    }
}
