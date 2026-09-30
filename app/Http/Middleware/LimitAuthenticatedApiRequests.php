<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitAuthenticatedApiRequests
{
    public function __construct(private readonly RateLimiter $rateLimiter) {}

    /**
     * Limit authenticated API requests by user and current tenant route context.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $limit = max(1, (int) config('api.rateLimit.authenticatedRequestsPerMinute'));
        $tenantScope = $request->route('tenantId') ?? 'global';
        $fingerprint = hash_hmac('sha256', $user->id.'|'.$tenantScope, (string) config('app.key'));
        $key = 'api-rate-limit:'.$fingerprint;

        if ($this->rateLimiter->tooManyAttempts($key, $limit)) {
            return ApiErrorResponse::json($request, 'API_RATE_LIMITED', 'O limite de solicitações foi atingido. Tente novamente em instantes.', Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', (string) $this->rateLimiter->availableIn($key));
        }

        $this->rateLimiter->hit($key, 60);

        return $next($request);
    }
}
