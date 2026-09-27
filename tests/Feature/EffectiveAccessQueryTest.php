<?php

namespace Tests\Feature;

use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Administration\EffectiveAccessQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EffectiveAccessQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_direct_and_group_factors_only_within_the_tenant(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Tenant', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => 'ACTIVE']);
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        $role = AuthorizationRole::query()->create(['idTenant' => $tenant->id, 'key' => 'tenant.billing.viewer', 'displayName' => 'Billing', 'description' => '', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $permission->id]);
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $group = AuthorizationGroup::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Billing', 'scope' => 'TENANT', 'active' => true]);
        DB::table('auth_group_user')->insert(['idGroup' => $group->id, 'idUser' => $user->id]);
        AuthorizationRestriction::query()->create(['idPermission' => $permission->id, 'idGroup' => $group->id, 'idTenant' => $tenant->id, 'scope' => 'TENANT', 'active' => true]);
        AuthorizationRestriction::query()->create(['idPermission' => $permission->id, 'idUser' => $user->id, 'idTenant' => $otherTenant->id, 'scope' => 'TENANT', 'active' => true]);
        $resourceTypeId = DB::table('auth_resource_type')->where('key', 'tenant.folder')->value('id');
        DB::table('auth_resource_relation')->insert(['idResourceType' => $resourceTypeId, 'resourceId' => 123, 'idGroup' => $group->id, 'idTenant' => $tenant->id, 'relationKey' => 'READ', 'scope' => 'TENANT', 'active' => true, 'createdAt' => now(), 'updatedAt' => now()]);

        $access = app(EffectiveAccessQuery::class)->forTenantMember($user, $tenant->id);

        $this->assertSame('ACTIVE', $access['membership']['state']);
        $this->assertSame([$role->id], array_column($access['directRoles'], 'id'));
        $this->assertSame([$group->id], array_column($access['groups'], 'id'));
        $this->assertSame([$group->id], array_column($access['restrictions'], 'subjectId'));
        $this->assertSame([123], array_column($access['resourceRelations'], 'resourceId'));
    }
}
