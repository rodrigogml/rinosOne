<?php

namespace Tests\Feature;

use App\Infrastructure\FinancialInstitution\FinancialInstitutionSource;
use App\Infrastructure\FinancialInstitution\FinancialInstitutionSourceRecord;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\MaintenanceAdministrativeAudit;
use App\Models\User;
use App\Services\Maintenance\IbgeTerritoryMaintenanceService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unprivileged_user_cannot_discover_or_trigger_the_financial_institution_routine(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/platform/maintenance/routines')
            ->assertOk()
            ->assertExactJson(['routines' => []]);
        $this->actingAs($user)
            ->getJson('/api/v1/platform/maintenance/routines/financial-institution-catalog')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MAINTENANCE_ROUTINE_NOT_AVAILABLE');
        $this->actingAs($user)
            ->postJson('/api/v1/platform/maintenance/routines/financial-institution-catalog/actions/SYNCHRONIZE')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'MAINTENANCE_ACTION_NOT_ALLOWED');

        $this->assertSame('REFUSED_NOT_AUTHORIZED', MaintenanceAdministrativeAudit::sole()->outcome);
    }

    public function test_operator_can_read_the_financial_institution_routine_and_request_synchronization(): void
    {
        $this->bindFinancialInstitutionSource();
        $operator = $this->maintenanceOperator();

        $this->actingAs($operator)
            ->getJson('/api/v1/platform/maintenance/routines')
            ->assertOk()
            ->assertJsonPath('routines.0.routineKey', 'financial-institution-catalog')
            ->assertJsonPath('routines.0.state', 'NOT_EXECUTED')
            ->assertJsonPath('routines.0.capabilities.canSynchronize', true);
        $this->actingAs($operator)
            ->postJson('/api/v1/platform/maintenance/routines/financial-institution-catalog/actions/SYNCHRONIZE')
            ->assertOk()
            ->assertJsonPath('execution.state', 'SUCCEEDED')
            ->assertJsonPath('execution.createdCount', 1);
        $this->actingAs($operator)
            ->getJson('/api/v1/platform/maintenance/audit')
            ->assertOk()
            ->assertJsonPath('administrativeAudits.0.action', 'SYNCHRONIZE')
            ->assertJsonPath('administrativeAudits.0.outcome', 'ACCEPTED');
    }

    public function test_ibge_reader_can_read_the_routine_but_cannot_trigger_it(): void
    {
        $reader = $this->ibgeReader();

        $this->actingAs($reader)
            ->getJson('/api/v1/platform/maintenance/routines/locality-ibge-territory-catalog')
            ->assertOk()
            ->assertJsonPath('routine.routineKey', IbgeTerritoryMaintenanceService::ROUTINE_KEY)
            ->assertJsonPath('routine.state', 'NOT_EXECUTED')
            ->assertJsonPath('routine.capabilities.canSynchronize', false)
            ->assertJsonPath('routine.administrativeAudits', []);
        $this->actingAs($reader)
            ->postJson('/api/v1/platform/maintenance/routines/locality-ibge-territory-catalog/actions/SYNCHRONIZE')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MAINTENANCE_ROUTINE_NOT_AVAILABLE');
    }

    private function maintenanceOperator(): User
    {
        $user = User::factory()->create();
        $role = AuthorizationRole::query()
            ->where('key', 'platform.maintenance.financial-institution.operator')
            ->firstOrFail();
        AuthorizationRoleAssignment::query()->create([
            'idRole' => $role->id,
            'idUser' => $user->id,
            'idTenant' => null,
            'state' => 'ACTIVE',
        ]);

        return $user;
    }

    private function ibgeReader(): User
    {
        return $this->platformUserWithRole('platform.maintenance.locality-ibge.reader');
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
