<?php

namespace Tests\Feature;

use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Maintenance\MaintenanceRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class MaintenanceRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_retention_configuration_defaults_to_ninety_days(): void
    {
        $this->assertSame(90, config('maintenance.historyRetentionDays'));
        $this->assertSame(90, config('maintenance.administrativeAuditRetentionDays'));
    }

    public function test_administrative_audit_cannot_be_updated_or_deleted_manually(): void
    {
        $audit = $this->administrativeAudit(CarbonImmutable::parse('2026-12-25'));
        $audit->outcome = 'REJECTED';

        try {
            $audit->save();
            $this->fail('Expected immutable audit update to be rejected.');
        } catch (LogicException) {
            // Expected: audits are append-only until expiry.
        }

        $this->expectException(LogicException::class);

        $audit->delete();
    }

    public function test_retention_removes_only_records_whose_own_expiration_has_arrived(): void
    {
        $now = CarbonImmutable::parse('2026-09-26 12:00:00');
        $expiredExecution = $this->executionHistory($now->subSecond());
        $activeExecution = $this->executionHistory($now->addSecond());
        $expiredAudit = $this->administrativeAudit($now->subSecond());
        $activeAudit = $this->administrativeAudit($now->addSecond());

        $result = app(MaintenanceRetentionService::class)->purgeExpired($now);

        $this->assertSame(1, $result->deletedExecutionHistoryCount);
        $this->assertSame(1, $result->deletedAdministrativeAuditCount);
        $this->assertModelMissing($expiredExecution);
        $this->assertModelExists($activeExecution);
        $this->assertModelMissing($expiredAudit);
        $this->assertModelExists($activeAudit);
    }

    private function executionHistory(CarbonImmutable $expiresAt): MaintenanceExecutionHistory
    {
        return MaintenanceExecutionHistory::query()->create([
            'routineKey' => 'financial-institution-catalog',
            'triggerType' => 'MANUAL',
            'state' => 'SUCCEEDED',
            'startedAt' => CarbonImmutable::parse('2026-09-26 11:00:00'),
            'completedAt' => CarbonImmutable::parse('2026-09-26 11:01:00'),
            'summary' => 'Synchronized safely.',
            'expiresAt' => $expiresAt,
        ]);
    }

    private function administrativeAudit(CarbonImmutable $expiresAt): MaintenanceAdministrativeAudit
    {
        return MaintenanceAdministrativeAudit::query()->create([
            'idPerformedByUser' => User::factory()->create()->id,
            'routineKey' => 'financial-institution-catalog',
            'action' => 'SYNCHRONIZE',
            'outcome' => 'ACCEPTED',
            'occurredAt' => CarbonImmutable::parse('2026-09-26 11:00:00'),
            'expiresAt' => $expiresAt,
        ]);
    }
}
