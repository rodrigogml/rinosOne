<?php

namespace Tests\Feature;

use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\MaintenanceAdministrativeAudit;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Maintenance\FinancialInstitutionMaintenanceService;
use App\Services\Maintenance\MaintenanceHubService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class MaintenanceHubServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_the_explicit_financial_institution_routine_without_execution_history(): void
    {
        $unprivilegedUser = User::factory()->create();
        $this->assertSame([], app(MaintenanceHubService::class)->listKnownRoutines($unprivilegedUser));

        $routines = app(MaintenanceHubService::class)->listKnownRoutines($this->maintenanceOperator());

        $this->assertCount(1, $routines);
        $this->assertSame(FinancialInstitutionMaintenanceService::ROUTINE_KEY, $routines[0]->routineKey);
        $this->assertSame('NOT_EXECUTED', $routines[0]->state);
        $this->assertSame('Diária', $routines[0]->scheduleDescription);
        $this->assertTrue($routines[0]->supportsManualSynchronization);
        $this->assertNull($routines[0]->lastExecution);
    }

    public function test_financial_institution_detail_exposes_safe_history_and_administrative_audit_data(): void
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        MaintenanceExecutionHistory::query()->create([
            'routineKey' => FinancialInstitutionMaintenanceService::ROUTINE_KEY,
            'triggerType' => 'SCHEDULED',
            'state' => 'SUCCEEDED',
            'startedAt' => $now->subMinutes(2),
            'completedAt' => $now->subMinute(),
            'summary' => 'Created 2; updated 3.',
            'details' => ['createdCount' => 2, 'updatedCount' => 3],
            'expiresAt' => $now->addDays(90),
        ]);
        MaintenanceExecutionHistory::query()->create([
            'routineKey' => FinancialInstitutionMaintenanceService::ROUTINE_KEY,
            'triggerType' => 'MANUAL',
            'state' => 'FAILED',
            'startedAt' => $now,
            'completedAt' => $now->addSecond(),
            'summary' => 'The official source could not be synchronized.',
            'details' => ['failureCode' => 'RuntimeException'],
            'expiresAt' => $now->addDays(90),
        ]);
        MaintenanceExecutionHistory::query()->create([
            'routineKey' => FinancialInstitutionMaintenanceService::ROUTINE_KEY,
            'triggerType' => 'SCHEDULED',
            'state' => 'RUNNING',
            'startedAt' => $now->addMinutes(2),
            'expiresAt' => $now->addDays(90),
        ]);
        MaintenanceAdministrativeAudit::query()->create([
            'idPerformedByUser' => User::factory()->create()->id,
            'routineKey' => FinancialInstitutionMaintenanceService::ROUTINE_KEY,
            'action' => 'SYNCHRONIZE',
            'outcome' => 'ACCEPTED',
            'occurredAt' => $now,
            'expiresAt' => $now->addDays(90),
        ]);

        $detail = app(MaintenanceHubService::class)->financialInstitutionCatalog($this->maintenanceOperator());

        $this->assertSame('RUNNING', $detail->state);
        $this->assertSame('RUNNING', $detail->lastExecution->state);
        $this->assertNull($detail->lastExecution->createdCount);
        $this->assertNull($detail->lastExecution->updatedCount);
        $this->assertCount(3, $detail->executionHistory);
        $this->assertSame('FAILED', $detail->executionHistory[1]->state);
        $this->assertNull($detail->executionHistory[1]->createdCount);
        $this->assertSame(2, $detail->executionHistory[2]->createdCount);
        $this->assertSame(3, $detail->executionHistory[2]->updatedCount);
        $this->assertCount(1, $detail->administrativeAudits);
        $this->assertSame('ACCEPTED', $detail->administrativeAudits[0]->outcome);
    }

    public function test_history_limits_are_bounded(): void
    {
        $service = app(MaintenanceHubService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->financialInstitutionCatalog($this->maintenanceOperator(), historyLimit: 0);
    }

    public function test_forwards_a_manual_financial_institution_request_to_its_specific_motor(): void
    {
        $lock = Cache::lock('maintenance.financial-institution-catalog', config('financial-institutions.maintenance_lock_seconds'));
        $this->assertTrue($lock->get());

        try {
            $result = app(MaintenanceHubService::class)->requestFinancialInstitutionSynchronization($this->maintenanceOperator());
        } finally {
            $lock->release();
        }

        $this->assertFalse($result->accepted);
        $this->assertSame('REFUSED_ALREADY_RUNNING', MaintenanceAdministrativeAudit::sole()->outcome);
    }

    public function test_refuses_an_unauthorized_manual_request_without_starting_the_routine(): void
    {
        $result = app(MaintenanceHubService::class)->requestFinancialInstitutionSynchronization(User::factory()->create());

        $this->assertFalse($result->accepted);
        $this->assertNull($result->executionHistory);
        $this->assertSame('REFUSED_NOT_AUTHORIZED', MaintenanceAdministrativeAudit::sole()->outcome);
        $this->assertSame(0, MaintenanceExecutionHistory::query()->count());
    }

    public function test_platform_administrator_inherits_the_financial_institution_maintenance_permissions(): void
    {
        $user = $this->platformAdministrator();

        $this->assertCount(1, app(MaintenanceHubService::class)->listKnownRoutines($user));
    }

    private function maintenanceOperator(): User
    {
        return $this->platformUserWithRole('platform.maintenance.financial-institution.operator');
    }

    private function platformAdministrator(): User
    {
        return $this->platformUserWithRole('platform.administrator');
    }

    private function platformUserWithRole(string $roleKey): User
    {
        $user = User::factory()->create();
        $role = AuthorizationRole::query()
            ->where('key', $roleKey)
            ->firstOrFail();
        AuthorizationRoleAssignment::query()->create([
            'idRole' => $role->id,
            'idUser' => $user->id,
            'idTenant' => null,
            'state' => 'ACTIVE',
        ]);

        return $user;
    }
}
