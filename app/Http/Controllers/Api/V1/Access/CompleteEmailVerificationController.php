<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Domain\Access\Account\CompletedEmailVerification;
use App\Domain\Access\Security\SecurityEvent;
use App\Http\Requests\Access\CompleteEmailVerificationLinkRequest;
use App\Http\Requests\Access\CompleteEmailVerificationRequest;
use App\Services\Access\AuthenticationSessionService;
use App\Services\Access\EmailVerificationService;
use App\Services\Access\SecurityEventLogger;
use Illuminate\Http\JsonResponse;

class CompleteEmailVerificationController
{
    public function byCode(CompleteEmailVerificationRequest $request, EmailVerificationService $verification): JsonResponse
    {
        return $this->respond($verification->confirmByCode($request->string('challengeId')->toString(), $request->string('code')->toString(), $request->ip() ?? 'unknown'));
    }

    public function byLink(CompleteEmailVerificationLinkRequest $request, EmailVerificationService $verification): JsonResponse
    {
        return $this->respond($verification->confirmByLink($request->string('challengeId')->toString(), $request->string('token')->toString()));
    }

    private function respond(?CompletedEmailVerification $completed): JsonResponse
    {
        if ($completed === null) {
            app(SecurityEventLogger::class)->record(SecurityEvent::AuthenticationChallengeRejected);

            return response()->json(['code' => 'INVALID_CREDENTIAL', 'message' => 'Não foi possível confirmar esta solicitação.'], 400);
        }
        $cookie = app(AuthenticationSessionService::class)->start($completed->user, $completed->rememberMeRequested);
        $user = $completed->user;
        app(SecurityEventLogger::class)->record(SecurityEvent::AuthenticationSucceeded, $user->id);

        $response = response()->json(['user' => ['id' => $user->id, 'displayName' => $user->displayName]], 201);

        return $cookie === null ? $response : $response->withCookie($cookie);
    }
}
