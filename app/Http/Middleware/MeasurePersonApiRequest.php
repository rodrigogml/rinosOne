<?php

namespace App\Http\Middleware;

use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Services\Person\PersonApiMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Records safe aggregate latency and outcome measurements for People API calls. */
final class MeasurePersonApiRequest
{
    public function __construct(private readonly PersonApiMetrics $metrics) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = null;
        $errorCode = null;
        $operation = $request->route()?->getName() ?? 'people.unknown';

        try {
            $response = $next($request);
            $payload = json_decode((string) $response->getContent(), true);
            $errorCode = is_array($payload) ? ($payload['error']['code'] ?? $payload['code'] ?? null) : null;

            return $response;
        } catch (PersonVersionConflictException $exception) {
            $errorCode = 'PERSON_VERSION_CONFLICT';

            throw $exception;
        } finally {
            $status = $response?->getStatusCode() ?? Response::HTTP_INTERNAL_SERVER_ERROR;
            $elapsedMilliseconds = max(0, (int) ((hrtime(true) - $startedAt) / 1_000_000));
            $this->metrics->record(
                $operation,
                $errorCode === 'PERSON_VERSION_CONFLICT' ? Response::HTTP_CONFLICT : $status,
                $elapsedMilliseconds,
                is_string($errorCode) ? $errorCode : null,
            );
            $this->emitLatencyAlertWhenNeeded($operation);
        }
    }

    private function emitLatencyAlertWhenNeeded(string $operation): void
    {
        try {
            if (! $this->metrics->alertIsActive($operation)) {
                return;
            }
            $key = 'person:api-latency-alert:'.$operation;
            if (Cache::add($key, true, now()->addMinutes((int) config('person.performanceAlertWindowMinutes')))) {
                Log::warning('People API latency alert threshold reached.', [
                    'operation' => $operation,
                    'thresholdMilliseconds' => (int) config('person.performanceAlertP95Milliseconds'),
                    'windowMinutes' => (int) config('person.performanceAlertWindowMinutes'),
                ]);
            }
        } catch (Throwable) {
            // Observability must never compromise a People request.
        }
    }
}
