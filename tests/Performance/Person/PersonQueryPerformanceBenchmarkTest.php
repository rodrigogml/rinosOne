<?php

namespace Tests\Performance\Person;

use App\Domain\Person\PersonStatus;
use App\Services\Person\PersonQueryService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PersonFixtureBuilder;
use Tests\TestCase;

/**
 * Deterministic local regression guard for the People catalogue query.
 *
 * It seeds only synthetic data and executes the same page query 25 times.
 * This is intentionally a local regression budget, not a production SLA;
 * deployment validation must repeat it against the target MySQL topology.
 */
class PersonQueryPerformanceBenchmarkTest extends TestCase
{
    private const PERSON_COUNT = 100000;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'rinos-person-benchmark-') ?: throw new \RuntimeException('Unable to create a temporary benchmark database.');
        config()->set('database.connections.person_performance', ['driver' => 'sqlite', 'database' => $this->databasePath, 'prefix' => '']);
        DB::purge('person_performance');
        Schema::connection('person_performance')->create('person', function ($table): void {
            $table->id();
            $table->string('personType');
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('displayName');
            $table->string('cpf')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('version');
            $table->index(['status', 'displayName', 'id']);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::connection('person_performance')->create('personContact', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('contactType');
            $table->string('value');
            $table->string('normalizedValue');
            $table->index(['idPerson', 'normalizedValue']);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        foreach (['personAddress', 'personBankAccount', 'personPixKey'] as $tableName) {
            Schema::connection('person_performance')->create($tableName, function ($table): void {
                $table->id();
                $table->unsignedBigInteger('idPerson');
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
            });
        }
        PersonFixtureBuilder::insertPeople(DB::connection('person_performance'), self::PERSON_COUNT);
    }

    protected function tearDown(): void
    {
        DB::purge('person_performance');
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_paginated_catalogue_query_remains_under_the_local_p95_regression_budget(): void
    {
        $databasePath = $this->databasePath;
        $samples = Concurrency::driver('process')->run(array_fill(0, 25, static function () use ($databasePath): float {
            config()->set('database.connections.person_performance', ['driver' => 'sqlite', 'database' => $databasePath, 'prefix' => '']);
            DB::purge('person_performance');
            $startedAt = hrtime(true);
            $page = app(PersonQueryService::class)->paginate(DB::connection('person_performance'), 'Pessoa sintética 099', null, PersonStatus::ACTIVE, 1, 50);
            if ($page->count() > 50) {
                throw new \RuntimeException('The People catalogue returned more records than the requested page size.');
            }

            return (hrtime(true) - $startedAt) / 1_000_000;
        }));
        sort($samples);
        $p95 = $samples[(int) ceil(count($samples) * .95) - 1];

        $this->assertLessThanOrEqual(
            (float) config('person.performanceAlertP95Milliseconds'),
            $p95,
            sprintf('People query p95 regression budget exceeded: %.2f ms.', $p95),
        );
    }
}
