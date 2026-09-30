<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PersonRouteContractTest extends TestCase
{
    public function test_every_people_mutation_requires_context_rate_limit_and_idempotency(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        foreach (['people.store', 'people.update', 'people.duplicate', 'people.inactivate', 'people.reactivate', 'people.destroy'] as $name) {
            $middleware = $routes->get($name)?->gatherMiddleware() ?? [];
            $this->assertContains('api.rate-limit', $middleware, $name);
            $this->assertContains('api.idempotency', $middleware, $name);
            $this->assertContains('person.tenant-context:tenant.people.'.match ($name) {
                'people.store' => 'create', 'people.update' => 'update', 'people.duplicate' => 'duplicate', 'people.inactivate' => 'inactivate', 'people.reactivate' => 'reactivate', default => 'delete',
            }, $middleware, $name);
        }
    }
}
