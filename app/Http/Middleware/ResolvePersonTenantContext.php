<?php

namespace App\Http\Middleware;

use App\Domain\Person\Exception\PersonAccessDeniedException;
use App\Services\Person\PersonTenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePersonTenantContext
{
    public function __construct(private readonly PersonTenantContext $context) {}

    /**
     * Resolve the authorized tenant connection for a People API operation.
     *
     * The connection is carried only in the request attributes and is never
     * inferred from client input by the controller or domain services.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $tenantId = $request->route('tenantId');
        $principal = $request->user();

        if (! is_numeric($tenantId) || $principal === null) {
            return $this->denied();
        }

        try {
            $request->attributes->set('person.tenant.connection', $this->context->connectionFor($principal, (int) $tenantId, $permission));
        } catch (PersonAccessDeniedException) {
            return $this->denied();
        }

        return $next($request);
    }

    private function denied(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'PERSON_ACCESS_DENIED',
                'message' => 'Ação não permitida para esta organização.',
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
