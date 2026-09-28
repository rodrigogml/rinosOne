<?php

namespace App\Http\Controllers\Api\V1\Authorization;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationServiceCredential;
use App\Services\Authorization\Advanced\ServiceIdentityAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceIdentityAuthorizationController extends Controller
{
    public function check(Request $request, ServiceIdentityAuthorizationService $authorization): JsonResponse
    {
        $validated = $request->validate(['permissionKey' => ['required', 'string', 'max:160']]);
        /** @var AuthorizationServiceCredential $credential */
        $credential = $request->attributes->get('serviceCredential');

        return response()->json(['allowed' => $authorization->allows($credential, $validated['permissionKey'])]);
    }
}
