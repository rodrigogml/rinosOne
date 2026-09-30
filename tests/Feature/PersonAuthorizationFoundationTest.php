<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationDecision;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Person\Exception\PersonAccessDeniedException;
use App\Domain\Person\PersonPermission;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Person\PersonTenantContext;
use App\Services\Tenant\TenantContextResolver;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PersonAuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_administrator_receives_every_people_capability_only_in_its_active_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);
        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => TenantState::Active]);
        $authorization = app(AuthorizationService::class);

        foreach (PersonPermission::all() as $permissionKey) {
            $this->assertDatabaseHas('auth_permission', [
                'key' => $permissionKey,
                'scope' => AuthorizationScope::Tenant->value,
                'systemManaged' => true,
                'active' => true,
            ]);
            $this->assertTrue($authorization->check($user, $permissionKey, AuthorizationScope::Tenant, $tenant->id)->allowed);
            $this->assertFalse($authorization->check($user, $permissionKey, AuthorizationScope::Tenant, $otherTenant->id)->allowed);
        }
    }

    public function test_people_context_rejects_a_missing_membership_before_resolving_a_tenant_connection(): void
    {
        $user = User::factory()->create();
        $resolver = Mockery::mock(TenantContextResolver::class);
        $resolver->shouldReceive('resolveMembership')->once()->with($user, '91')->andReturnNull();
        $resolver->shouldNotReceive('resolveConnection');
        $authorization = Mockery::mock(AuthorizationService::class);
        $authorization->shouldNotReceive('check');

        $this->expectException(PersonAccessDeniedException::class);
        (new PersonTenantContext($resolver, $authorization))->connectionFor($user, 91, PersonPermission::READ);
    }

    public function test_people_context_returns_only_the_connection_resolved_for_the_authorized_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $membership = TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);
        $connection = Mockery::mock(ConnectionInterface::class);
        $resolver = Mockery::mock(TenantContextResolver::class);
        $resolver->shouldReceive('resolveMembership')->once()->with($user, (string) $tenant->id)->andReturn($membership);
        $resolver->shouldReceive('resolveConnection')->once()->with($user, (string) $tenant->id)->andReturn($connection);
        $authorization = Mockery::mock(AuthorizationService::class);
        $authorization->shouldReceive('check')->once()
            ->with($user, PersonPermission::UPDATE, AuthorizationScope::Tenant, $tenant->id)
            ->andReturn(new AuthorizationDecision(true, 'GRANT_APPLIES'));

        $this->assertSame($connection, (new PersonTenantContext($resolver, $authorization))->connectionFor($user, $tenant->id, PersonPermission::UPDATE));
    }

    private function activeTenantFor(User $user): Tenant
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        DB::table('auth_role_assignment')->insert([
            'idRole' => $role->id,
            'idUser' => $user->id,
            'idTenant' => $tenant->id,
            'state' => 'ACTIVE',
        ]);

        return $tenant;
    }
}
