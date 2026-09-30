<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Produces the error envelope negotiated by an API contract.
 *
 * The shared API policies predate the People aggregate and retain their
 * top-level envelope for existing endpoints. People endpoints explicitly
 * negotiate the aggregate envelope under `error`, including policy errors
 * returned before their controller executes.
 */
final class ApiErrorResponse
{
    /** @param array<string, mixed> $extra */
    public static function json(Request $request, string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        $payload = ['code' => $code, 'message' => $message, ...$extra];

        return response()->json(self::usesPeopleEnvelope($request) ? ['error' => $payload] : $payload, $status);
    }

    public static function normalizeExisting(Request $request, Response $response): Response
    {
        if (! self::usesPeopleEnvelope($request)) {
            return $response;
        }

        $payload = json_decode((string) $response->getContent(), true);
        if (! is_array($payload) || ! isset($payload['code']) || ! is_string($payload['code'])) {
            return $response;
        }

        $normalized = response()->json(['error' => $payload], $response->getStatusCode());
        if ($response->headers->has('Retry-After')) {
            $normalized->headers->set('Retry-After', (string) $response->headers->get('Retry-After'));
        }

        return $normalized;
    }

    private static function usesPeopleEnvelope(Request $request): bool
    {
        return $request->is('api/v1/tenants/*/people*');
    }
}
