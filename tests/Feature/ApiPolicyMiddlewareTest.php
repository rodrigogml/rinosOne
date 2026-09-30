<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantState;
use App\Http\Middleware\EnforceJsonRequestSize;
use App\Http\Middleware\LimitAuthenticatedApiRequests;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Models\ApiIdempotencyRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Api\ApiPagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class ApiPolicyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagination_uses_the_global_default_and_clamps_client_values(): void
    {
        config(['api.pagination.defaultPerPage' => 50, 'api.pagination.maximumPerPage' => 200]);

        $default = ApiPagination::fromRequest(Request::create('/people'));
        $clamped = ApiPagination::fromRequest(Request::create('/people?page=0&perPage=999'));

        $this->assertSame(1, $default->page);
        $this->assertSame(50, $default->perPage);
        $this->assertSame(1, $clamped->page);
        $this->assertSame(200, $clamped->perPage);
    }

    public function test_json_size_middleware_rejects_a_payload_above_the_global_limit(): void
    {
        config(['api.request.maximumJsonBytes' => 8]);
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"value":123}');

        $response = app(EnforceJsonRequestSize::class)->handle($request, fn () => response()->json(['accepted' => true]));

        $this->assertSame(413, $response->getStatusCode());
        $this->assertSame('API_REQUEST_TOO_LARGE', $response->getData(true)['code']);
    }

    public function test_json_size_policy_uses_the_people_error_envelope_for_a_people_mutation(): void
    {
        config(['api.request.maximumJsonBytes' => 8]);
        $request = Request::create('/api/v1/tenants/31/people', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"value":123}');

        $response = app(EnforceJsonRequestSize::class)->handle($request, fn () => response()->json(['accepted' => true]));

        $this->assertSame(413, $response->getStatusCode());
        $this->assertSame('API_REQUEST_TOO_LARGE', $response->getData(true)['error']['code']);
    }

    public function test_authenticated_rate_limit_is_scoped_to_the_user_and_tenant(): void
    {
        config(['api.rateLimit.authenticatedRequestsPerMinute' => 1]);
        $user = User::factory()->create();
        $tenantId = $this->tenantId();
        $middleware = app(LimitAuthenticatedApiRequests::class);

        $first = $middleware->handle($this->requestFor($user, $tenantId), fn () => response()->json(['accepted' => true]));
        $second = $middleware->handle($this->requestFor($user, $tenantId), fn () => response()->json(['accepted' => true]));
        $otherTenant = $middleware->handle($this->requestFor($user, $this->tenantId()), fn () => response()->json(['accepted' => true]));

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode());
        $this->assertSame('API_RATE_LIMITED', $second->getData(true)['error']['code']);
        $this->assertSame(200, $otherTenant->getStatusCode());
    }

    public function test_idempotency_middleware_replays_a_completed_mutation_without_reexecuting_it(): void
    {
        $user = User::factory()->create();
        $middleware = app(RequireIdempotencyKey::class);
        $tenantId = $this->tenantId();
        $key = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6a';
        $attempts = 0;

        $first = $middleware->handle($this->requestFor($user, $tenantId, $key), function () use (&$attempts) {
            $attempts++;

            return response()->json(['attempts' => $attempts], 201);
        });
        $second = $middleware->handle($this->requestFor($user, $tenantId, $key), function () use (&$attempts) {
            $attempts++;

            return response()->json(['attempts' => $attempts], 201);
        });

        $this->assertSame(1, $attempts);
        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(201, $second->getStatusCode());
        $this->assertSame('{"attempts":1}', $second->getContent());
        $this->assertSame('COMPLETED', ApiIdempotencyRecord::sole()->state);
    }

    public function test_idempotency_middleware_rejects_invalid_or_conflicting_keys_and_expires_completed_records(): void
    {
        $user = User::factory()->create();
        $middleware = app(RequireIdempotencyKey::class);
        $tenantId = $this->tenantId();
        $invalid = $middleware->handle($this->requestFor($user, $tenantId, 'invalid'), fn () => response()->json(['accepted' => true]));
        $key = '4b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6a';
        $attempts = 0;
        config(['api.idempotency.retentionHours' => 1]);

        $middleware->handle($this->requestFor($user, $tenantId, $key, '{"name":"A"}'), function () use (&$attempts) {
            $attempts++;

            return response()->json(['attempts' => $attempts]);
        });
        $conflict = $middleware->handle($this->requestFor($user, $tenantId, $key, '{"name":"B"}'), fn () => response()->json(['accepted' => true]));
        $this->travel(2)->hours();
        $expired = $middleware->handle($this->requestFor($user, $tenantId, $key, '{"name":"A"}'), function () use (&$attempts) {
            $attempts++;

            return response()->json(['attempts' => $attempts]);
        });

        $this->assertSame(400, $invalid->getStatusCode());
        $this->assertSame('API_IDEMPOTENCY_KEY_INVALID', $invalid->getData(true)['error']['code']);
        $this->assertSame(409, $conflict->getStatusCode());
        $this->assertSame('API_IDEMPOTENCY_KEY_CONFLICT', $conflict->getData(true)['error']['code']);
        $this->assertSame(2, $attempts);
        $this->assertSame('{"attempts":2}', $expired->getContent());
    }

    private function requestFor(User $user, int $tenantId = 31, ?string $key = null, string $content = '{}'): Request
    {
        $request = Request::create('/api/v1/tenants/'.$tenantId.'/people', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ], $content);
        $route = new Route(['POST'], 'api/v1/tenants/{tenantId}/people', static fn () => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);
        $request->setUserResolver(static fn (): User => $user);

        return $request;
    }

    private function tenantId(): int
    {
        return Tenant::query()->create([
            'displayName' => 'Tenant de teste',
            'state' => TenantState::Active,
        ])->id;
    }
}
