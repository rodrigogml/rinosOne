<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Http\Requests\Access\PasswordlessSessionRequest;
use App\Services\Access\PasswordlessSessionService;
use Illuminate\Http\JsonResponse;

class PasswordlessSessionController
{
    public function request(PasswordlessSessionRequest $request, PasswordlessSessionService $service): JsonResponse
    {
        $result = $service->request($request->string('email')->toString(), $request->ip() ?? 'unknown', $request->boolean('rememberMe'));
        if (! $result->decision->allowed) {
            return response()->json(['code' => 'RATE_LIMITED', 'message' => 'Não foi possível processar esta solicitação agora. Tente novamente mais tarde.'], 429)
                ->header('Retry-After', (string) $result->decision->retryAfterSeconds);
        }

        return response()->json([
            'message' => 'Se possível, enviaremos instruções para o endereço informado.',
            'challengeId' => $result->challengeId,
            'resendAvailableInSeconds' => config('access.authentication.emailResendCooldownSeconds'),
        ], 202);
    }
}
