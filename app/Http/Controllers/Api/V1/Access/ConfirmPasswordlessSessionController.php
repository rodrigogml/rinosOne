<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Http\Requests\Access\ConfirmPasswordlessSessionLinkRequest;
use App\Http\Requests\Access\ConfirmPasswordlessSessionRequest;
use App\Services\Access\AuthenticationSessionService;
use App\Services\Access\PasswordlessSessionService;
use Illuminate\Http\JsonResponse;

class ConfirmPasswordlessSessionController
{
    public function __invoke(ConfirmPasswordlessSessionRequest $request, PasswordlessSessionService $service, AuthenticationSessionService $sessions): JsonResponse
    {
        $result = $service->confirmCode($request->integer('challengeId'), $request->string('code')->toString(), $request->ip() ?? 'unknown');

        return $this->respond($result, $sessions);
    }

    public function byLink(ConfirmPasswordlessSessionLinkRequest $request, PasswordlessSessionService $service, AuthenticationSessionService $sessions): JsonResponse
    {
        return $this->respond($service->confirmLink($request->integer('challengeId'), $request->string('token')->toString()), $sessions);
    }

    private function respond(?array $result, AuthenticationSessionService $sessions): JsonResponse
    {
        if ($result === null) {
            return response()->json(['code' => 'INVALID_CREDENTIAL', 'message' => 'Não foi possível confirmar esta solicitação.'], 400);
        }
        [$user, $remember] = $result;
        $cookie = $sessions->start($user, $remember);
        $response = response()->json(['user' => ['id' => $user->id, 'displayName' => $user->displayName]], 201);

        return $cookie === null ? $response : $response->withCookie($cookie);
    }
}
