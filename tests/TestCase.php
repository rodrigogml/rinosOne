<?php

namespace Tests;

use App\Domain\Tenant\SchemaCompatibilityDecision;
use App\Services\Tenant\GlobalSchemaCompatibilityService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery\MockInterface;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GlobalSchemaCompatibilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('decide')
                ->byDefault()
                ->andReturn(SchemaCompatibilityDecision::compatible());
        });
    }
}
