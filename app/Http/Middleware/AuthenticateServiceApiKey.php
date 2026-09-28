<?php

namespace App\Http\Middleware;

use App\Services\Authorization\Advanced\AuthorizationServiceIdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authenticates a technical caller without establishing a human web session. */
class AuthenticateServiceApiKey
{
    public function __construct(private readonly AuthorizationServiceIdentityService $identities) {}

    public function handle(Request $request, Closure $next): Response
    {
        $credential = $this->identities->validateApiKey((string) $request->header('X-Rinos-Api-Key'));
        if ($credential === null) {
            return response()->json(['code' => 'SERVICE_API_KEY_INVALID', 'message' => 'A credencial técnica não é válida.'], 401);
        }
        $request->attributes->set('serviceCredential', $credential);

        return $next($request);
    }
}
