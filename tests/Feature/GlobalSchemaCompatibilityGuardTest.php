<?php

namespace Tests\Feature;

use App\Domain\Tenant\SchemaCompatibilityDecision;
use App\Services\Tenant\GlobalSchemaCompatibilityService;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class GlobalSchemaCompatibilityGuardTest extends TestCase
{
    public function test_it_blocks_an_api_request_before_authentication_and_controller_execution(): void
    {
        $this->setCompatibility(false);

        $this->getJson('/api/v1/auth/session')
            ->assertStatus(503)
            ->assertExactJson([
                'error' => [
                    'code' => 'PLATFORM_SCHEMA_INCOMPATIBLE',
                    'message' => 'A plataforma está temporariamente indisponível para atualização.',
                ],
            ]);
    }

    public function test_it_blocks_a_write_before_its_route_handler_runs(): void
    {
        $this->setCompatibility(false);
        $handlerWasCalled = false;

        Route::post('/api/v1/schema-compatibility-write-probe', function () use (&$handlerWasCalled) {
            $handlerWasCalled = true;

            return response()->json(['stored' => true]);
        });

        $this->postJson('/api/v1/schema-compatibility-write-probe')
            ->assertStatus(503);

        $this->assertFalse($handlerWasCalled);
    }

    public function test_it_returns_a_safe_web_response_without_the_application_shell(): void
    {
        $this->setCompatibility(false);

        $this->get('/access/people')
            ->assertStatus(503)
            ->assertSee('Atualização em andamento')
            ->assertSee('Tentar novamente')
            ->assertSee('assets/brand/crest-192.png?v=20260930', false)
            ->assertSee('assets/brand/logo-768.png?v=20260930', false)
            ->assertDontSee('id="app"', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_it_keeps_only_operational_health_routes_available(): void
    {
        $compatibility = Mockery::mock(GlobalSchemaCompatibilityService::class);
        $compatibility->shouldNotReceive('decide');
        $this->app->instance(GlobalSchemaCompatibilityService::class, $compatibility);

        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_it_allows_a_functional_request_when_the_schema_is_compatible(): void
    {
        $this->setCompatibility(true);

        Route::get('/api/v1/schema-compatibility-read-probe', static fn () => response()->json(['available' => true]));

        $this->getJson('/api/v1/schema-compatibility-read-probe')
            ->assertOk()
            ->assertExactJson(['available' => true]);
    }

    private function setCompatibility(bool $compatible): void
    {
        $service = Mockery::mock(GlobalSchemaCompatibilityService::class);
        $service->shouldReceive('decide')
            ->byDefault()
            ->andReturn($compatible
                ? SchemaCompatibilityDecision::compatible()
                : SchemaCompatibilityDecision::incompatible());
        $this->app->instance(GlobalSchemaCompatibilityService::class, $service);
    }
}
