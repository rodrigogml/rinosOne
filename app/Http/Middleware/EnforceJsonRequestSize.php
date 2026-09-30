<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceJsonRequestSize
{
    /**
     * Reject JSON payloads beyond the configured global API limit.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return $next($request);
        }

        $maximumBytes = max(1, (int) config('api.request.maximumJsonBytes'));
        $declaredLength = $request->headers->get('Content-Length');

        if (($declaredLength !== null && ctype_digit($declaredLength) && (int) $declaredLength > $maximumBytes)
            || strlen($request->getContent()) > $maximumBytes) {
            return ApiErrorResponse::json($request, 'API_REQUEST_TOO_LARGE', 'A solicitação excede o tamanho máximo permitido.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
