<?php

namespace Tests\Feature;

use App\Infrastructure\EconomicIndicator\EconomicIndicatorSource;
use App\Infrastructure\EconomicIndicator\EconomicIndicatorSourceRecord;
use App\Infrastructure\EconomicIndicator\EconomicPtaxSource;
use App\Infrastructure\EconomicIndicator\EconomicPtaxSourceRecord;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\EconomicIndicatorSeries;
use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Maintenance\EconomicIndicatorMaintenanceService;
use App\Services\Maintenance\MaintenanceHubService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EconomicIndicatorMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_synchronization_creates_an_audit_and_technical_history(): void
    {
        $this->bindSources();

        $result = app(EconomicIndicatorMaintenanceService::class)->synchronizeManually(User::factory()->create());

        $this->assertTrue($result->accepted);
        $this->assertSame('SUCCEEDED', $result->executionHistory?->state);
        $this->assertSame('MANUAL', $result->executionHistory?->triggerType);
        $this->assertCount(7, $result->executionHistory?->details['series'] ?? []);
        $this->assertSame('BCB_SGS', $result->executionHistory?->details['series']['SELIC_DAILY']['sourceKey']);
        $this->assertArrayHasKey('from', $result->executionHistory?->details['series']['SELIC_DAILY']);
        $this->assertArrayHasKey('through', $result->executionHistory?->details['series']['SELIC_DAILY']);
        $this->assertArrayHasKey('recalculatedCount', $result->executionHistory?->details['series']['SELIC_DAILY']);
        $this->assertSame(1, MaintenanceExecutionHistory::query()->count());
        $this->assertSame('ACCEPTED', MaintenanceAdministrativeAudit::sole()->outcome);
    }

    public function test_running_routine_refuses_a_manual_request_and_records_the_refusal(): void
    {
        $lock = Cache::lock('maintenance.economic-indicators', config('economic-indicators.maintenance_lock_seconds'));
        $this->assertTrue($lock->get());

        try {
            $result = app(EconomicIndicatorMaintenanceService::class)->synchronizeManually(User::factory()->create());
        } finally {
            $lock->release();
        }

        $this->assertFalse($result->accepted);
        $this->assertSame('ALREADY_RUNNING', $result->refusalCode);
        $this->assertSame('REFUSED_ALREADY_RUNNING', MaintenanceAdministrativeAudit::sole()->outcome);
    }

    public function test_maintenance_hub_exposes_the_routine_only_to_its_platform_operator(): void
    {
        $operator = User::factory()->create();
        $this->bindSources();
        app(EconomicIndicatorMaintenanceService::class)->synchronizeManually($operator);
        $role = AuthorizationRole::query()
            ->where('key', 'platform.maintenance.economic-indicators.operator')
            ->firstOrFail();
        AuthorizationRoleAssignment::query()->create([
            'idRole' => $role->id,
            'idUser' => $operator->id,
            'idTenant' => null,
            'state' => 'ACTIVE',
        ]);

        $routine = app(MaintenanceHubService::class)->economicIndicators($operator);

        $this->assertNotNull($routine);
        $this->assertSame(EconomicIndicatorMaintenanceService::ROUTINE_KEY, $routine->routineKey);
        $this->assertTrue($routine->supportsManualSynchronization);
        $this->assertArrayHasKey('series', $routine->lastExecution?->details ?? []);
        $this->assertSame('BCB_SGS', $routine->lastExecution?->details['series']['SELIC_DAILY']['sourceKey']);
        $this->assertNull(app(MaintenanceHubService::class)->economicIndicators(User::factory()->create()));
        $this->actingAs($operator)
            ->getJson('/api/v1/platform/maintenance/routines/economic-indicators')
            ->assertOk()
            ->assertJsonPath('routine.routineKey', EconomicIndicatorMaintenanceService::ROUTINE_KEY)
            ->assertJsonPath('routine.lastExecution.details.series.SELIC_DAILY.sourceKey', 'BCB_SGS')
            ->assertJsonPath('routine.capabilities.canSynchronize', true);
    }

    public function test_operational_command_runs_the_managed_routine_for_a_selected_series(): void
    {
        $this->bindSources();
        config()->set('economic-indicators.historical_chunk_days', 50_000);

        $this->artisan('maintenance:economic-indicators:sync', ['--series' => ['SELIC_DAILY']])
            ->assertExitCode(0);

        $this->assertSame(1, EconomicIndicatorSeries::query()->count());
        $this->assertSame('SELIC_DAILY', EconomicIndicatorSeries::sole()->code);
        $this->assertSame('SCHEDULED', MaintenanceExecutionHistory::sole()->triggerType);
        $this->assertSame('SUCCEEDED', MaintenanceExecutionHistory::sole()->state);
        $this->assertSame(['SELIC_DAILY'], array_keys(MaintenanceExecutionHistory::sole()->details['series']));
    }

    private function bindSources(): void
    {
        $this->app->bind(EconomicIndicatorSource::class, fn (): EconomicIndicatorSource => new class implements EconomicIndicatorSource
        {
            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return [new EconomicIndicatorSourceRecord(CarbonImmutable::instance($to)->startOfDay(), '0.250000000000', 'test:'.$series->code)];
            }
        });
        $this->app->bind(EconomicPtaxSource::class, fn (): EconomicPtaxSource => new class implements EconomicPtaxSource
        {
            public function fetch(EconomicIndicatorSeries $series, CarbonInterface $from, CarbonInterface $to): array
            {
                return [new EconomicPtaxSourceRecord(CarbonImmutable::instance($to)->startOfDay(), CarbonImmutable::instance($to), '5.000000000000', '5.100000000000', '5.050000000000', 'test:'.$series->code)];
            }
        });
    }
}
