<?php

namespace Tests\Feature;

use App\Infrastructure\Locality\IbgeTerritoryCatalog;
use App\Infrastructure\Locality\IbgeTerritoryMunicipalityRecord;
use App\Infrastructure\Locality\IbgeTerritorySource;
use App\Infrastructure\Locality\IbgeTerritoryStateRecord;
use App\Models\MaintenanceExecutionHistory;
use App\Services\Maintenance\IbgeTerritoryMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class IbgeTerritoryMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_first_evaluation_runs_immediately_then_waits_until_the_next_month(): void
    {
        $this->bindCatalogSource();
        $firstRunAt = CarbonImmutable::parse('2026-09-27 10:00:00', 'America/Sao_Paulo');
        CarbonImmutable::setTestNow($firstRunAt);

        $firstHistory = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue($firstRunAt);
        $sameMonthHistory = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue($firstRunAt->addDays(29));
        $nextMonthHistory = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue($firstRunAt->addMonthNoOverflow());

        $this->assertNotNull($firstHistory);
        $this->assertSame('SUCCEEDED', $firstHistory->state);
        $this->assertSame('SCHEDULED', $firstHistory->triggerType);
        $this->assertNull($sameMonthHistory);
        $this->assertNotNull($nextMonthHistory);
        $this->assertSame(2, MaintenanceExecutionHistory::query()->count());
    }

    public function test_failure_retries_only_after_the_configured_delay_and_keeps_safe_history(): void
    {
        $failedAt = CarbonImmutable::parse('2026-09-27 10:00:00', 'America/Sao_Paulo');
        CarbonImmutable::setTestNow($failedAt);
        $this->app->bind(IbgeTerritorySource::class, fn (): IbgeTerritorySource => new class implements IbgeTerritorySource
        {
            public function fetch(): IbgeTerritoryCatalog
            {
                throw new RuntimeException('IBGE unavailable with internal connection details');
            }
        });

        $failure = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue($failedAt);
        $beforeRetry = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue($failedAt->addHours(5));

        $this->bindCatalogSource();
        CarbonImmutable::setTestNow($failedAt->addHours(6));
        $retry = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue($failedAt->addHours(6));

        $this->assertNotNull($failure);
        $this->assertSame('FAILED', $failure->state);
        $this->assertSame(['failureCode' => RuntimeException::class], $failure->details);
        $this->assertNull($beforeRetry);
        $this->assertNotNull($retry);
        $this->assertSame('SUCCEEDED', $retry->state);
        $this->assertSame(2, MaintenanceExecutionHistory::query()->count());
    }

    public function test_concurrent_evaluation_is_refused_without_a_second_history_record(): void
    {
        $this->bindCatalogSource();
        $lock = Cache::lock(
            'maintenance.'.IbgeTerritoryMaintenanceService::ROUTINE_KEY,
            config('localities.ibge_territory_maintenance.lock_seconds'),
        );
        $this->assertTrue($lock->get());

        try {
            $history = app(IbgeTerritoryMaintenanceService::class)->synchronizeWhenDue();
        } finally {
            $lock->release();
        }

        $this->assertNull($history);
        $this->assertSame(0, MaintenanceExecutionHistory::query()->count());
    }

    private function bindCatalogSource(): void
    {
        $this->app->bind(IbgeTerritorySource::class, fn (): IbgeTerritorySource => new class implements IbgeTerritorySource
        {
            public function fetch(): IbgeTerritoryCatalog
            {
                return new IbgeTerritoryCatalog(
                    states: [new IbgeTerritoryStateRecord('35', 'SP', 'São Paulo')],
                    municipalities: [new IbgeTerritoryMunicipalityRecord('3550308', '35', 'São Paulo')],
                );
            }
        });
    }
}
