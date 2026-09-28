<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationEvaluationContext;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationPolicy;
use App\Models\AuthorizationPolicyBinding;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\AuthorizationResourceType;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationRestrictionService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthorizationPolicyEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_policy_only_reduces_an_existing_tenant_grant_using_server_context(): void
    {
        [$user, $tenant, $permission] = $this->grant();
        $policy = AuthorizationPolicy::query()->create(['idTenant' => $tenant->id, 'scope' => 'TENANT', 'key' => 'amount-limit', 'contextFingerprint' => "tenant:{$tenant->id}", 'type' => 'AMOUNT_MAXIMUM', 'version' => 1, 'definition' => ['all' => [['type' => 'AMOUNT_MAXIMUM', 'maximum' => 100]]], 'active' => true]);
        AuthorizationPolicyBinding::query()->create(['idPolicy' => $policy->id, 'idPermission' => $permission->id, 'active' => true]);
        $authorization = app(AuthorizationService::class);

        $this->assertFalse($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertTrue($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id, context: new AuthorizationEvaluationContext(amount: 100))->allowed);
        $this->assertFalse($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id, context: new AuthorizationEvaluationContext(amount: 101))->allowed);
    }

    public function test_a_restriction_remains_stronger_than_a_satisfied_policy(): void
    {
        [$user, $tenant, $permission] = $this->grant();
        app(AuthorizationRestrictionService::class)->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);

        $decision = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id, context: new AuthorizationEvaluationContext(amount: 1));

        $this->assertFalse($decision->allowed);
        $this->assertSame('RESTRICTION_APPLIES', $decision->reasonCode);
    }

    public function test_a_resource_qualified_policy_only_affects_the_bound_resource(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $shared = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $other = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Other', 'state' => 'ACTIVE']);
        $sharedReference = new ResourceReference('personal.folder', $shared->id, AuthorizationScope::Personal);
        app(AuthorizationResourceRelationService::class)->create($sharedReference, 'READ', $collaborator);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $other->id, AuthorizationScope::Personal), 'READ', $collaborator);
        $policy = AuthorizationPolicy::query()->create(['scope' => 'PERSONAL', 'key' => 'shared-limit', 'contextFingerprint' => 'personal', 'type' => 'AMOUNT_MAXIMUM', 'version' => 1, 'definition' => ['all' => [['type' => 'AMOUNT_MAXIMUM', 'maximum' => 5]]], 'active' => true]);
        AuthorizationPolicyBinding::query()->create(['idPolicy' => $policy->id, 'idPermission' => AuthorizationPermission::query()->where('key', 'personal.folder.read')->firstOrFail()->id, 'idResourceType' => AuthorizationResourceType::query()->where('key', 'personal.folder')->firstOrFail()->id, 'resourceId' => $shared->id, 'active' => true]);
        $authorization = app(AuthorizationService::class);

        $this->assertFalse($authorization->check($collaborator, 'personal.folder.read', AuthorizationScope::Personal, resource: $sharedReference, context: new AuthorizationEvaluationContext(amount: 6))->allowed);
        $this->assertTrue($authorization->check($collaborator, 'personal.folder.read', AuthorizationScope::Personal, resource: new ResourceReference('personal.folder', $other->id, AuthorizationScope::Personal), context: new AuthorizationEvaluationContext(amount: 6))->allowed);
    }

    public function test_a_cache_failure_does_not_bypass_a_satisfied_policy(): void
    {
        [$user, $tenant, $permission] = $this->grant();
        config()->set('authorization.decisionCacheSeconds', 300);
        Cache::shouldReceive('get')->once()->andThrow(new \RuntimeException('cache unavailable'));

        $decision = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id, context: new AuthorizationEvaluationContext(amount: 1));

        $this->assertTrue($decision->allowed);
    }

    /** @return array{User, Tenant, AuthorizationPermission} */
    private function grant(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Policy tenant', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        return [$user, $tenant, $permission];
    }
}
