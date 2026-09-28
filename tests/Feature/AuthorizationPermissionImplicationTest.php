<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\AuthorizationRestriction;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationPermissionImplicationService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuthorizationPermissionImplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_grant_of_the_source_permission_satisfies_the_implied_permission_and_invalidates_cached_decisions(): void
    {
        [$user, $tenant, $source, $implied] = $this->tenantContext();
        $role = $this->roleWith($source);
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $authorizer = app(AuthorizationService::class);
        $this->assertFalse($authorizer->check($user, $implied->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        app(AuthorizationPermissionImplicationService::class)->create($source, $implied);

        $this->assertTrue($authorizer->check($user, $implied->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
    }

    public function test_a_restriction_of_the_implied_permission_has_precedence_over_the_source_grant(): void
    {
        [$user, $tenant, $source, $implied] = $this->tenantContext();
        $role = $this->roleWith($source);
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        app(AuthorizationPermissionImplicationService::class)->create($source, $implied);
        AuthorizationRestriction::query()->create(['idPermission' => $implied->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'scope' => 'TENANT', 'active' => true]);

        $decision = app(AuthorizationService::class)->check($user, $implied->key, AuthorizationScope::Tenant, $tenant->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('RESTRICTION_APPLIES', $decision->reasonCode);
    }

    public function test_implications_reject_cross_scope_and_cycles(): void
    {
        [, , $source, $implied] = $this->tenantContext();
        $platform = AuthorizationPermission::query()->create(['key' => 'platform.implication.read', 'displayName' => 'Platform read', 'description' => 'Platform.', 'scope' => 'PLATFORM', 'systemManaged' => false, 'active' => true]);
        $service = app(AuthorizationPermissionImplicationService::class);

        try {
            $service->create($source, $platform);
            $this->fail('Cross-scope implications must be rejected.');
        } catch (LogicException) {
        }
        $service->create($source, $implied);

        $this->expectException(LogicException::class);
        $service->create($implied, $source);
    }

    /** @return array{0: User, 1: Tenant, 2: AuthorizationPermission, 3: AuthorizationPermission} */
    private function tenantContext(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Implication', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $source = AuthorizationPermission::query()->create(['key' => 'tenant.implication.manage', 'displayName' => 'Manage', 'description' => 'Manage.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $implied = AuthorizationPermission::query()->create(['key' => 'tenant.implication.read', 'displayName' => 'Read', 'description' => 'Read.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);

        return [$user, $tenant, $source, $implied];
    }

    private function roleWith(AuthorizationPermission $permission): AuthorizationRole
    {
        $role = AuthorizationRole::query()->create(['key' => 'tenant.implication.role', 'displayName' => 'Role', 'description' => 'Role.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $permission->id]);

        return $role;
    }
}
