<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Services\Authorization\AuthorizationPermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationPermissionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_permission_is_added_to_the_system_administrator_role(): void
    {
        $permission = app(AuthorizationPermissionCatalog::class)->register(
            'tenant.account.manage',
            'Manage accounts',
            'Allows financial account management.',
            AuthorizationScope::Tenant,
        );

        $administratorId = DB::table('auth_role')->where('key', 'tenant.administrator')->value('id');

        $this->assertTrue(DB::table('auth_role_permission')
            ->where('idRole', $administratorId)
            ->where('idPermission', $permission->id)
            ->exists());
    }

    public function test_existing_permission_cannot_be_registered_in_a_different_scope(): void
    {
        $catalog = app(AuthorizationPermissionCatalog::class);
        $catalog->register('personal.file.read', 'Read file', 'Allows file reading.', AuthorizationScope::Personal);

        $this->expectException(\LogicException::class);

        $catalog->register('personal.file.read', 'Read file', 'Allows file reading.', AuthorizationScope::Tenant);
    }

    public function test_administration_permissions_are_scoped_and_granted_only_to_the_matching_system_administrator(): void
    {
        $tenantAdministratorId = DB::table('auth_role')->where('key', 'tenant.administrator')->value('id');
        $platformAdministratorId = DB::table('auth_role')->where('key', 'platform.administrator')->value('id');

        foreach (['tenant.authorization.read', 'tenant.authorization.manage'] as $key) {
            $permission = DB::table('auth_permission')->where('key', $key)->first();
            $this->assertSame('TENANT', $permission->scope);
            $this->assertTrue(DB::table('auth_role_permission')->where('idRole', $tenantAdministratorId)->where('idPermission', $permission->id)->exists());
        }

        foreach (['platform.authorization.tenant.read', 'platform.authorization.tenant.manage'] as $key) {
            $permission = DB::table('auth_permission')->where('key', $key)->first();
            $this->assertSame('PLATFORM', $permission->scope);
            $this->assertTrue(DB::table('auth_role_permission')->where('idRole', $platformAdministratorId)->where('idPermission', $permission->id)->exists());
        }
    }
}
