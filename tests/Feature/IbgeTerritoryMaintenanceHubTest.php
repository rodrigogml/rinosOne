<?php

namespace Tests\Feature;

use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\MaintenanceExecutionHistory;
use App\Models\User;
use App\Services\Maintenance\IbgeTerritoryMaintenanceService;
use App\Services\Maintenance\MaintenanceHubService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IbgeTerritoryMaintenanceHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_sees_the_explicit_ibge_routine_without_manual_capability_or_administrative_audits(): void
    {
        $routine = app(MaintenanceHubService::class)->ibgeTerritoryCatalog($this->ibgeReader());

        $this->assertNotNull($routine);
        $this->assertSame(IbgeTerritoryMaintenanceService::ROUTINE_KEY, $routine->routineKey);
        $this->assertSame('NOT_EXECUTED', $routine->state);
        $this->assertSame('Inicial automática e mensal', $routine->scheduleDescription);
        $this->assertFalse($routine->supportsManualSynchronization);
        $this->assertSame([], $routine->administrativeAudits);
    }

    public function test_reader_receives_only_safe_ibge_history_details(): void
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        MaintenanceExecutionHistory::query()->create([
            'routineKey' => IbgeTerritoryMaintenanceService::ROUTINE_KEY,
            'triggerType' => 'SCHEDULED',
            'state' => 'FAILED',
            'startedAt' => $now->subMinute(),
            'completedAt' => $now,
            'summary' => 'Não foi possível sincronizar a fonte oficial.',
            'details' => [
                'failureCode' => 'RuntimeException',
                'sourceUrl' => 'https://must-not-be-exposed.invalid',
            ],
            'expiresAt' => $now->addDays(90),
        ]);

        $routine = app(MaintenanceHubService::class)->ibgeTerritoryCatalog($this->ibgeReader());

        $this->assertSame('FAILED', $routine->state);
        $this->assertSame(['failureCode' => 'RuntimeException'], $routine->lastExecution->details);
    }

    public function test_ibge_reader_cannot_read_financial_institution_routine(): void
    {
        $reader = $this->ibgeReader();
        $routines = app(MaintenanceHubService::class)->listKnownRoutines($reader);

        $this->assertCount(1, $routines);
        $this->assertSame(IbgeTerritoryMaintenanceService::ROUTINE_KEY, $routines[0]->routineKey);
        $this->assertNull(app(MaintenanceHubService::class)->financialInstitutionCatalog($reader));
    }

    private function ibgeReader(): User
    {
        $user = User::factory()->create();
        $role = AuthorizationRole::query()
            ->where('key', 'platform.maintenance.locality-ibge.reader')
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
