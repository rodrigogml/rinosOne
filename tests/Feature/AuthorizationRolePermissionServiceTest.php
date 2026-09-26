<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationRoleType;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Services\Authorization\AuthorizationRolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationRolePermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_an_active_permission_only_to_a_role_of_the_same_scope(): void
    {
        $role = AuthorizationRole::query()->create([
            'key' => 'tenant.account.manager', 'displayName' => 'Account manager', 'description' => 'Manages accounts.',
            'scope' => AuthorizationScope::Tenant->value, 'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false, 'active' => true,
        ]);
        $permission = AuthorizationPermission::query()->create([
            'key' => 'tenant.account.manage', 'displayName' => 'Manage accounts', 'description' => 'Manages accounts.',
            'scope' => AuthorizationScope::Tenant->value, 'systemManaged' => false, 'active' => true,
        ]);

        app(AuthorizationRolePermissionService::class)->grant($role, $permission);

        $this->assertTrue(DB::table('auth_role_permission')->where('idRole', $role->id)->where('idPermission', $permission->id)->exists());
    }

    public function test_it_rejects_a_permission_from_another_scope(): void
    {
        $role = AuthorizationRole::query()->create(['key' => 'personal.manager', 'displayName' => 'Personal', 'description' => 'Personal.', 'scope' => 'PERSONAL', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        $permission = AuthorizationPermission::query()->create(['key' => 'tenant.account.manage', 'displayName' => 'Manage', 'description' => 'Manage.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);

        $this->expectException(\LogicException::class);
        app(AuthorizationRolePermissionService::class)->grant($role, $permission);
    }
}
