<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\Exception\PlatformSchemaIncompatibleException;
use App\Services\Tenant\GlobalSchemaCompatibilityService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops functional HTTP requests before authentication or controllers when
 * the global schema does not match the distributed migration catalog.
 */
final class EnsureGlobalSchemaCompatibility
{
    public function __construct(
        private readonly GlobalSchemaCompatibilityService $compatibility,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isOperationalHealthRequest($request) || $this->compatibility->decide()->isCompatible()) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->apiUnavailable();
        }

        return response()->view('schema-incompatible', status: Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function isOperationalHealthRequest(Request $request): bool
    {
        return $request->is('up') || $request->is('api/v1/health');
    }

    private function apiUnavailable(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => PlatformSchemaIncompatibleException::ERROR_CODE,
                'message' => 'A plataforma está temporariamente indisponível para atualização.',
            ],
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
