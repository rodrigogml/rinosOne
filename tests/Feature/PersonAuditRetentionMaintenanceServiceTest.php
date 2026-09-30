<?php

namespace Tests\Feature;

use App\Models\MaintenanceExecutionHistory;
use App\Services\Maintenance\PersonAuditRetentionMaintenanceService;
use App\Services\Person\PersonAuditRetentionResult;
use App\Services\Person\PersonAuditRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PersonAuditRetentionMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_retention_records_a_safe_execution_history_for_the_maintenance_hub(): void
    {
        $now = CarbonImmutable::parse('2026-09-29 10:00:00', 'America/Sao_Paulo');
        $retention = Mockery::mock(PersonAuditRetentionService::class);
        $retention->shouldReceive('purgeExpired')
            ->once()
            ->withArgs(fn (CarbonImmutable $scheduledAt): bool => $scheduledAt->equalTo($now))
            ->andReturn(new PersonAuditRetentionResult(2, 7));

        $history = (new PersonAuditRetentionMaintenanceService($retention))->purgeScheduled($now);

        $this->assertNotNull($history);
        $this->assertSame('SCHEDULED', $history->triggerType);
        $this->assertSame('SUCCEEDED', $history->state);
        $this->assertSame('Auditoria de Pessoas retida conforme a política configurada.', $history->summary);
        $this->assertSame(['processedTenantCount' => 2, 'deletedEventCount' => 7], $history->details);
        $this->assertSame(1, MaintenanceExecutionHistory::query()->count());
    }

    public function test_scheduled_retention_refuses_a_concurrent_execution_without_creating_history(): void
    {
        $lock = Cache::lock('maintenance.'.PersonAuditRetentionMaintenanceService::ROUTINE_KEY, 3600);
        $this->assertTrue($lock->get());
        $retention = Mockery::mock(PersonAuditRetentionService::class);

        try {
            $history = (new PersonAuditRetentionMaintenanceService($retention))->purgeScheduled();
        } finally {
            $lock->release();
        }

        $this->assertNull($history);
        $this->assertSame(0, MaintenanceExecutionHistory::query()->count());
    }
}
