<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationPermission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationRestrictionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthorizationRestrictionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_one_audited_tenant_restriction_for_an_eligible_subject(): void
    {
        [$tenant, $user, $permission] = $this->tenantContext();
        $service = app(AuthorizationRestrictionService::class);

        $restriction = $service->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id, actorUserId: $user->id);
        $sameRestriction = $service->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);

        $this->assertSame($restriction->id, $sameRestriction->id);
        $this->assertDatabaseHas('auth_restriction', ['id' => $restriction->id, 'idTenant' => $tenant->id, 'idUser' => $user->id, 'active' => true]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.restriction.created', 'targetId' => $restriction->id]);
    }

    public function test_it_rejects_an_ambiguous_subject_or_a_subject_outside_the_tenant(): void
    {
        [$tenant, $user, $permission] = $this->tenantContext();
        $otherUser = User::factory()->create();
        $service = app(AuthorizationRestrictionService::class);

        try {
            $service->create($permission, null, null, AuthorizationScope::Tenant, $tenant->id);
            $this->fail('Expected a restriction without a subject to be rejected.');
        } catch (LogicException) {
            $this->expectException(LogicException::class);
            $service->create($permission, $otherUser, null, AuthorizationScope::Tenant, $tenant->id);
        }
    }

    public function test_it_applies_validity_with_an_inclusive_start_and_exclusive_end(): void
    {
        [$tenant, $user, $permission] = $this->tenantContext();
        $start = CarbonImmutable::parse('2026-09-26 10:00:00');
        $end = CarbonImmutable::parse('2026-09-26 11:00:00');
        $restriction = app(AuthorizationRestrictionService::class)->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id, $start, $end);

        $this->assertFalse($restriction->isEffectiveAt($start->subSecond()));
        $this->assertTrue($restriction->isEffectiveAt($start));
        $this->assertTrue($restriction->isEffectiveAt($end->subSecond()));
        $this->assertFalse($restriction->isEffectiveAt($end));
    }

    public function test_it_audits_activation_deactivation_validity_changes_and_removal(): void
    {
        [$tenant, $user, $permission] = $this->tenantContext();
        $service = app(AuthorizationRestrictionService::class);
        $restriction = $service->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);

        $service->deactivate($restriction);
        $service->activate($restriction);
        $service->updateValidity($restriction, CarbonImmutable::parse('2026-10-01 00:00:00'), null);
        $service->remove($restriction);

        $this->assertDatabaseMissing('auth_restriction', ['id' => $restriction->id]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.restriction.deactivated', 'targetId' => $restriction->id]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.restriction.activated', 'targetId' => $restriction->id]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.restriction.validity_updated', 'targetId' => $restriction->id]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.restriction.removed', 'targetId' => $restriction->id]);
    }

    private function tenantContext(): array
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $permission = AuthorizationPermission::query()->create([
            'key' => 'tenant.report.read',
            'displayName' => 'Read reports',
            'description' => 'Reads tenant reports.',
            'scope' => AuthorizationScope::Tenant->value,
            'systemManaged' => false,
            'active' => true,
        ]);

        return [$tenant, $user, $permission];
    }
}
