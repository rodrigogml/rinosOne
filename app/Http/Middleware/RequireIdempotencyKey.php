<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use App\Services\Api\ApiIdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequireIdempotencyKey
{
    public function __construct(private readonly ApiIdempotencyService $idempotency) {}

    /**
     * Reserve, complete or replay a mutation identified by an UUID v4 key.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $key) !== 1) {
            return ApiErrorResponse::json($request, 'API_IDEMPOTENCY_KEY_INVALID', 'A chave de idempotência deve ser um UUID v4 válido.', Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenantId = $request->route('tenantId');
        $tenantId = is_numeric($tenantId) ? (int) $tenantId : null;
        $operation = $request->route()?->getName() ?? $request->getMethod().' '.$request->route()?->uri();
        $fingerprint = hash('sha256', $request->getMethod().'|'.$operation.'|'.$request->getContent());
        $decision = $this->idempotency->begin($user, $tenantId, $operation, strtolower($key), $fingerprint);

        if ($decision instanceof Response) {
            return ApiErrorResponse::normalizeExisting($request, $decision);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->idempotency->forget($decision);

            throw $exception;
        }

        $this->idempotency->complete($decision, $response);

        return $response;
    }
}
