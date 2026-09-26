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
}
