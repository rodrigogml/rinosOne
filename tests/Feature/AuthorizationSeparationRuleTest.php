<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationSeparationRuleService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationSeparationRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_incompatible_pair_denies_an_effective_permission(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Separation', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $first = AuthorizationPermission::query()->create(['key' => 'tenant.operation.request', 'displayName' => 'Request', 'description' => 'Request operation.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $second = AuthorizationPermission::query()->create(['key' => 'tenant.operation.approve', 'displayName' => 'Approve', 'description' => 'Approve operation.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        foreach ([$first, $second] as $index => $permission) {
            $role = AuthorizationRole::query()->create(['key' => "tenant.operation.role.{$index}", 'displayName' => "Role {$index}", 'description' => 'Operation role.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
            DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $permission->id]);
            AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        }
        app(AuthorizationSeparationRuleService::class)->create($first, $second, AuthorizationScope::Tenant, $tenant->id);

        $decision = app(AuthorizationService::class)->check($user, $first->key, AuthorizationScope::Tenant, $tenant->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('SEPARATION_OF_DUTIES_APPLIES', $decision->reasonCode);
    }

    public function test_a_direct_role_assignment_cannot_create_an_incompatible_pair(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Write separation', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $first = AuthorizationPermission::query()->create(['key' => 'tenant.write.request', 'displayName' => 'Request', 'description' => 'Request.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $second = AuthorizationPermission::query()->create(['key' => 'tenant.write.approve', 'displayName' => 'Approve', 'description' => 'Approve.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $firstRole = $this->roleWith($first, 'request');
        $secondRole = $this->roleWith($second, 'approve');
        AuthorizationRoleAssignment::query()->create(['idRole' => $firstRole->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        app(AuthorizationSeparationRuleService::class)->create($first, $second, AuthorizationScope::Tenant, $tenant->id);

        $this->expectException(\LogicException::class);
        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($secondRole, $user, $tenant->id);
    }

    public function test_direct_role_assignment_blocks_any_conflicting_rule_in_a_set_without_exposing_permission_keys(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Set separation', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $request = AuthorizationPermission::query()->create(['key' => 'tenant.set.request', 'displayName' => 'Request', 'description' => 'Request.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $approve = AuthorizationPermission::query()->create(['key' => 'tenant.set.approve', 'displayName' => 'Approve', 'description' => 'Approve.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $settle = AuthorizationPermission::query()->create(['key' => 'tenant.set.settle', 'displayName' => 'Settle', 'description' => 'Settle.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $requestRole = $this->roleWith($request, 'set-request');
        $settleRole = $this->roleWith($settle, 'set-settle');
        $approveRole = $this->roleWith($approve, 'set-approve');
        AuthorizationRoleAssignment::query()->create(['idRole' => $requestRole->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        AuthorizationRoleAssignment::query()->create(['idRole' => $settleRole->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $service = app(AuthorizationSeparationRuleService::class);
        $service->create($request, $approve, AuthorizationScope::Tenant, $tenant->id);
        $service->create($settle, $approve, AuthorizationScope::Tenant, $tenant->id);

        try {
            app(AuthorizationRoleAssignmentService::class)->assignTenantRole($approveRole, $user, $tenant->id);
            $this->fail('The incompatible direct assignment was accepted.');
        } catch (\LogicException $exception) {
            $this->assertSame('The role assignment violates a separation of duties rule.', $exception->getMessage());
            $this->assertStringNotContainsString($request->key, $exception->getMessage());
            $this->assertStringNotContainsString($settle->key, $exception->getMessage());
            $this->assertStringNotContainsString($approve->key, $exception->getMessage());
        }
    }

    private function roleWith(AuthorizationPermission $permission, string $suffix): AuthorizationRole
    {
        $role = AuthorizationRole::query()->create(['key' => "tenant.write.role.{$suffix}", 'displayName' => "Role {$suffix}", 'description' => 'Role.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $permission->id]);

        return $role;
    }
}
