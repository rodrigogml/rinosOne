<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\CheckResourceAuthorizationBatchRequest;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizedPersonalWorkspaceFolderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return response()->json(['decisions' => $authorization->checkBatch($request->user(), $request->validated('checks'))]);
    }
}
