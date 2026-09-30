<?php

namespace Tests\Unit;

use App\Domain\Person\Deletion\PersonUsage;
use App\Domain\Person\Deletion\PersonUsageInspector;
use App\Services\Person\PersonDeletionUsageService;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Tests\TestCase;

class PersonDeletionUsageServiceTest extends TestCase
{
    public function test_it_aggregates_only_registered_usage_inspectors(): void
    {
        $first = new class implements PersonUsageInspector
        {
            public function inspect(ConnectionInterface $connection, int $personId): array
            {
                return [new PersonUsage('Financeiro', 'Resolva os lançamentos que utilizam esta pessoa.')];
            }
        };
        $second = new class implements PersonUsageInspector
        {
            public function inspect(ConnectionInterface $connection, int $personId): array
            {
                return [new PersonUsage('Contratos', 'Resolva os contratos que utilizam esta pessoa.')];
            }
        };
        $this->app->instance('test.person-usage-inspector.first', $first);
        $this->app->instance('test.person-usage-inspector.second', $second);
        $this->app->tag([
            'test.person-usage-inspector.first',
            'test.person-usage-inspector.second',
        ], PersonDeletionUsageService::INSPECTOR_TAG);

        $usages = (new PersonDeletionUsageService($this->app))->inspect(Mockery::mock(ConnectionInterface::class), 42);

        $this->assertSame('Financeiro', $usages[0]->module);
        $this->assertSame('Contratos', $usages[1]->module);
    }

    public function test_it_returns_no_usage_when_no_module_registers_an_inspector(): void
    {
        $usages = (new PersonDeletionUsageService($this->app))->inspect(Mockery::mock(ConnectionInterface::class), 42);

        $this->assertSame([], $usages);
    }
}
