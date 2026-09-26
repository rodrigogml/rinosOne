<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use App\Services\Tenant\TenantCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AuthorizationAuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_assignment_changes_in_the_same_transaction(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();

        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($role, $user, $tenant->id);

        $this->assertDatabaseHas('auth_audit_event', [
            'operation' => 'authorization.role_assignment.activated',
            'targetType' => 'authorization.role_assignment',
            'idTenant' => $tenant->id,
        ]);
    }

    public function test_a_rolled_back_transaction_does_not_persist_its_audit_event(): void
    {
        try {
            DB::transaction(function (): void {
                app(AuthorizationAuditLogger::class)->record(
                    'authorization.permission.registered',
                    'authorization.permission',
                    42,
                );

                throw new RuntimeException('Abort transaction.');
            });
        } catch (RuntimeException) {
            // The test intentionally aborts the transaction after recording the event.
        }

        $this->assertDatabaseMissing('auth_audit_event', ['operation' => 'authorization.permission.registered']);
    }

    public function test_tenant_creation_records_membership_and_assignment_with_the_same_correlation(): void
    {
        $creator = User::factory()->create();
        $correlationId = 'f9b1d049-9a61-4cf1-a3c3-a9afae53d883';

        app(TenantCreationService::class)->create($creator, 'Acme', $correlationId);

        $this->assertDatabaseHas('auth_audit_event', [
            'operation' => 'authorization.tenant_membership.activated',
            'idActorUser' => $creator->id,
            'correlationId' => $correlationId,
        ]);
        $this->assertDatabaseHas('auth_audit_event', [
            'operation' => 'authorization.role_assignment.activated',
            'idActorUser' => $creator->id,
            'correlationId' => $correlationId,
        ]);
    }

    public function test_it_redacts_sensitive_snapshot_values_and_rejects_application_mutation(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $event = app(AuthorizationAuditLogger::class)->record(
            'authorization.test.changed',
            'authorization.test',
            42,
            actorUserId: $actor->id,
            tenantId: $tenant->id,
            before: ['state' => 'ACTIVE', 'password' => 'secret-value', 'nested' => ['apiToken' => 'token-value']],
            after: ['state' => 'INACTIVE', 'authorizationHeader' => 'Bearer value'],
            correlationId: 'request-42',
        );

        $this->assertSame(['state' => 'ACTIVE', 'nested' => []], $event->before);
        $this->assertSame(['state' => 'INACTIVE'], $event->after);
        $this->assertSame($actor->id, $event->idActorUser);
        $this->assertSame($tenant->id, $event->idTenant);
        $this->assertSame('request-42', $event->correlationId);
        $this->assertDatabaseMissing('auth_audit_event', ['before' => json_encode(['password' => 'secret-value'])]);

        $this->expectException(LogicException::class);
        $event->update(['operation' => 'authorization.test.changed-again']);
    }

    public function test_it_rejects_application_deletion(): void
    {
        $event = app(AuthorizationAuditLogger::class)->record('authorization.test.changed', 'authorization.test', 42);

        $this->expectException(LogicException::class);
        $event->delete();
    }
}
