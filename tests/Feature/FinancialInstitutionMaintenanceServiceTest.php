<?php

namespace Tests\Feature;

use App\Infrastructure\FinancialInstitution\FinancialInstitutionSource;
use App\Infrastructure\FinancialInstitution\FinancialInstitutionSourceRecord;
use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Maintenance\FinancialInstitutionMaintenanceService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FinancialInstitutionMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_financial_institution_synchronization_creates_separate_audit_and_history(): void
    {
        $this->bindFinancialInstitutionSource();

        $result = app(FinancialInstitutionMaintenanceService::class)->synchronizeManually(User::factory()->create());
        $history = $result->executionHistory;

        $this->assertTrue($result->accepted);
        $this->assertNotNull($history);
        $this->assertSame('SUCCEEDED', $history->state);
        $this->assertSame('MANUAL', $history->triggerType);
        $this->assertSame(1, MaintenanceExecutionHistory::query()->count());
        $audit = MaintenanceAdministrativeAudit::sole();
        $this->assertSame(FinancialInstitutionMaintenanceService::ROUTINE_KEY, $audit->routineKey);
        $this->assertSame('SYNCHRONIZE', $audit->action);
        $this->assertSame('ACCEPTED', $audit->outcome);
    }

    public function test_scheduled_synchronization_creates_history_without_administrative_audit(): void
    {
        $this->bindFinancialInstitutionSource();

        $history = app(FinancialInstitutionMaintenanceService::class)->synchronizeScheduled();

        $this->assertNotNull($history);
        $this->assertSame('SUCCEEDED', $history->state);
        $this->assertSame('SCHEDULED', $history->triggerType);
        $this->assertSame(1, MaintenanceExecutionHistory::query()->count());
        $this->assertSame(0, MaintenanceAdministrativeAudit::query()->count());
    }

    public function test_manual_request_is_refused_and_audited_when_the_routine_is_already_running(): void
    {
        $lock = Cache::lock('maintenance.financial-institution-catalog', config('financial-institutions.maintenance_lock_seconds'));
        $this->assertTrue($lock->get());

        try {
            $result = app(FinancialInstitutionMaintenanceService::class)->synchronizeManually(User::factory()->create());
        } finally {
            $lock->release();
        }

        $this->assertFalse($result->accepted);
        $this->assertNull($result->executionHistory);
        $this->assertSame('REFUSED_ALREADY_RUNNING', MaintenanceAdministrativeAudit::sole()->outcome);
        $this->assertSame(0, MaintenanceExecutionHistory::query()->count());
    }

    private function bindFinancialInstitutionSource(): void
    {
        $this->app->bind(FinancialInstitutionSource::class, fn (): FinancialInstitutionSource => new class implements FinancialInstitutionSource
        {
            public function fetch(CarbonInterface $referenceDate): array
            {
                return [new FinancialInstitutionSourceRecord(
                    bcbEntityIdentifier: 'BCB-EXAMPLE',
                    bcbReferenceDate: $referenceDate,
                    sisbacenCode: '12345',
                    cnpj: '12AB34567890CD',
                    legalName: 'Instituição Exemplo S.A.',
                    reducedName: 'Instituição exemplo',
                    tradeName: null,
                    acronym: null,
                    bcbStatusCode: '3',
                    bcbStatusName: 'Autorizada em Atividade',
                    institutionTypeCode: '1',
                    institutionTypeName: 'Banco',
                )];
            }
        });
    }
}
