<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Http\Requests\Access\StartRegistrationRequest;
use App\Services\Access\RegistrationInitiationService;
use Illuminate\Http\JsonResponse;

class StartRegistrationController
{
    public function __invoke(
        StartRegistrationRequest $request,
        RegistrationInitiationService $registration,
    ): JsonResponse {
        $result = $registration->initiate(
            $request->string('email')->toString(),
            $request->ip() ?? 'unknown',
            $request->boolean('rememberMe'),
        );

        if (! $result->decision->allowed) {
            return response()->json([
                'code' => 'RATE_LIMITED',
                'message' => 'Não foi possível processar esta solicitação agora. Tente novamente mais tarde.',
            ], 429)->header('Retry-After', (string) $result->decision->retryAfterSeconds);
        }

        return response()->json([
            'message' => 'Se possível, enviaremos instruções para o endereço informado.',
            'challengeId' => $result->challengeId,
        ], 202);
    }
}
