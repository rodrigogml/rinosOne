<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationRestrictionService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Tests\TestCase;

class PolicyVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_and_invalidates_a_tenant_policy_version(): void
    {
        $versions = app(PolicyVersionService::class);

        $this->assertSame(1, $versions->current(AuthorizationScope::Tenant, 42));
        $this->assertSame(2, $versions->invalidate(AuthorizationScope::Tenant, 42));
        $this->assertSame(2, $versions->current(AuthorizationScope::Tenant, 42));
        $this->assertDatabaseHas('auth_policy_version', ['scope' => 'TENANT', 'idTenant' => 42, 'subjectFingerprint' => 'tenant:42', 'version' => 2]);
    }

    public function test_it_separates_non_tenant_contexts_and_rejects_invalid_contexts(): void
    {
        $versions = app(PolicyVersionService::class);

        $this->assertSame(1, $versions->current(AuthorizationScope::Platform));
        $this->assertSame(1, $versions->current(AuthorizationScope::Personal));

        $this->assertSame(1, $versions->current(AuthorizationScope::Tenant));

        $this->expectException(LogicException::class);
        $versions->current(AuthorizationScope::Tenant, 0);
    }

    public function test_a_restriction_change_invalidates_a_cached_tenant_allow_decision(): void
    {
        config()->set('authorization.decisionCacheSeconds', 300);
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Performance', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $authorization = app(AuthorizationService::class);
        $this->assertTrue($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        Cache::flush();
        $this->assertTrue($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        app(AuthorizationRestrictionService::class)->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);

        $decision = $authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('RESTRICTION_APPLIES', $decision->reasonCode);
    }
}
