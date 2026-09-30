<?php

namespace App\Services\Api;

use App\Models\ApiIdempotencyRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response;

class ApiIdempotencyService
{
    public function begin(User $user, ?int $tenantId, string $operation, string $key, string $requestFingerprint): ApiIdempotencyRecord|Response
    {
        $tenantScopeKey = $tenantId === null ? 'global' : 'tenant:'.$tenantId;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $existing = $this->find($user->id, $tenantScopeKey, $operation, $key);

            if ($existing !== null && $existing->expiresAt->isPast()) {
                $existing->delete();

                continue;
            }

            if ($existing !== null) {
                if (! hash_equals($existing->requestFingerprint, $requestFingerprint)) {
                    return $this->error('API_IDEMPOTENCY_KEY_CONFLICT', 'A chave de idempotência já foi usada com outra solicitação.', Response::HTTP_CONFLICT);
                }

                if ($existing->state === 'PENDING') {
                    return $this->error('API_IDEMPOTENCY_IN_PROGRESS', 'Uma solicitação idêntica ainda está sendo processada.', Response::HTTP_CONFLICT)
                        ->header('Retry-After', '1');
                }

                return response($existing->responseBody, $existing->responseStatus)
                    ->header('Content-Type', $existing->responseContentType ?? 'application/json');
            }

            try {
                return ApiIdempotencyRecord::query()->create([
                    'idUser' => $user->id,
                    'idTenant' => $tenantId,
                    'tenantScopeKey' => $tenantScopeKey,
                    'operation' => $operation,
                    'idempotencyKey' => $key,
                    'requestFingerprint' => $requestFingerprint,
                    'state' => 'PENDING',
                    'expiresAt' => now()->addHours(max(1, (int) config('api.idempotency.retentionHours'))),
                ]);
            } catch (QueryException) {
                // A unique key race is resolved by rereading the winner on the next iteration.
            }
        }

        return $this->error('API_IDEMPOTENCY_IN_PROGRESS', 'Uma solicitação idêntica ainda está sendo processada.', Response::HTTP_CONFLICT)
            ->header('Retry-After', '1');
    }

    public function complete(ApiIdempotencyRecord $record, Response $response): void
    {
        if ($response->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $record->delete();

            return;
        }

        $record->update([
            'state' => 'COMPLETED',
            'responseStatus' => $response->getStatusCode(),
            'responseContentType' => $response->headers->get('Content-Type'),
            'responseBody' => $response->getContent(),
        ]);
    }

    public function forget(ApiIdempotencyRecord $record): void
    {
        $record->delete();
    }

    private function find(int $userId, string $tenantScopeKey, string $operation, string $key): ?ApiIdempotencyRecord
    {
        return ApiIdempotencyRecord::query()
            ->where('idUser', $userId)
            ->where('tenantScopeKey', $tenantScopeKey)
            ->where('operation', $operation)
            ->where('idempotencyKey', $key)
            ->first();
    }

    private function error(string $code, string $message, int $status): Response
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }
}
