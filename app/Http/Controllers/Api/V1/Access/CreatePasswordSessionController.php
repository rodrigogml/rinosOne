<?php

namespace App\Http\Controllers\Api\V1\Access;

use App\Domain\Access\Account\AccountEligibilityService;
use App\Domain\Access\Email\EmailNormalizer;
use App\Http\Requests\Access\CreatePasswordSessionRequest;
use App\Models\User;
use App\Services\Access\AccessRateLimitService;
use App\Services\Access\AuthenticationSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class CreatePasswordSessionController
{
    public function __invoke(CreatePasswordSessionRequest $request, AccessRateLimitService $limits, AccountEligibilityService $eligibility, AuthenticationSessionService $sessions): JsonResponse
    {
        $email = EmailNormalizer::normalize($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();
        $decision = $limits->attemptPassword($email, $request->ip() ?? 'unknown', $user?->id);
        if (! $decision->allowed) {
            return response()->json(['code' => 'RATE_LIMITED', 'message' => 'Não foi possível processar esta solicitação agora. Tente novamente mais tarde.'], 429);
        }
        if ($user === null || ! $eligibility->canAuthenticate($user) || $user->passwordHash === null || ! Hash::check($request->string('password')->toString(), $user->passwordHash)) {
            return response()->json(['code' => 'INVALID_CREDENTIAL', 'message' => 'Não foi possível autenticar com as credenciais informadas.'], 400);
        }
        $cookie = $sessions->start($user, $request->boolean('rememberMe'));
        $response = response()->json(['user' => ['id' => $user->id, 'displayName' => $user->displayName]], 201);

        return $cookie === null ? $response : $response->withCookie($cookie);
    }
}
