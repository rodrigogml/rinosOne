<?php

namespace Tests\Performance\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationRestrictionService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use App\Services\Authorization\Resource\AuthorizedPersonalWorkspaceFolderQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationPerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_decision_paths_remain_within_the_regression_budget(): void
    {
        config()->set('authorization.decisionCacheSeconds', 0);
        $authorization = app(AuthorizationService::class);
        [$user, $tenant] = $this->tenantMember();
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();

        DB::table('auth_role_assignment')->insert(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $this->withinBudget('direct check', function () use ($authorization, $user, $tenant): void {
            foreach (range(1, 10) as $_) {
                $this->assertTrue($authorization->check($user, 'tenant.availability.manage', AuthorizationScope::Tenant, $tenant->id)->allowed);
            }
        });

        $parent = AuthorizationGroup::query()->create(['displayName' => 'Benchmark parent', 'scope' => 'TENANT', 'idTenant' => $tenant->id, 'active' => true]);
        $child = AuthorizationGroup::query()->create(['displayName' => 'Benchmark child', 'scope' => 'TENANT', 'idTenant' => $tenant->id, 'active' => true]);
        DB::table('auth_group_user')->insert(['idGroup' => $child->id, 'idUser' => $user->id]);
        DB::table('auth_group_group')->insert(['idParentGroup' => $parent->id, 'idChildGroup' => $child->id]);
        DB::table('auth_group_role_assignment')->insert(['idRole' => $role->id, 'idGroup' => $parent->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        DB::table('auth_role_assignment')->where('idUser', $user->id)->delete();
        $this->withinBudget('nested group check', fn () => $this->assertTrue($authorization->check($user, 'tenant.availability.manage', AuthorizationScope::Tenant, $tenant->id)->allowed));

        $owner = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'READ', $user);
        $resource = new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal);
        $this->withinBudget('resource check', fn () => $this->assertTrue($authorization->check($user, 'personal.folder.read', AuthorizationScope::Personal, resource: $resource)->allowed));

        $checks = array_fill(0, 20, ['permissionKey' => 'personal.folder.read', 'resource' => ['type' => 'personal.folder', 'id' => $folder->id]]);
        $this->withinBudget('batch check', fn () => $this->assertCount(20, $authorization->checkBatch($user, $checks)));

        foreach (range(1, 30) as $number) {
            WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => "Folder {$number}", 'state' => 'ACTIVE']);
        }
        $this->withinBudget('authorized listing', fn () => $this->assertCount(31, app(AuthorizedPersonalWorkspaceFolderQuery::class)->pageFor($user, 1, 50)));

        config()->set('authorization.decisionCacheSeconds', 300);
        app(AuthorizationRestrictionService::class)->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);
        $this->withinBudget('invalidation decision', fn () => $this->assertFalse($authorization->check($user, 'tenant.availability.manage', AuthorizationScope::Tenant, $tenant->id)->allowed));
    }

    private function withinBudget(string $scenario, callable $operation): void
    {
        $startedAt = hrtime(true);
        $operation();
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
        $this->assertLessThanOrEqual((float) config('authorization.benchmarkMaxMilliseconds'), $elapsedMilliseconds, "Authorization benchmark exceeded its regression budget: {$scenario} ({$elapsedMilliseconds} ms).");
    }

    /** @return array{User, Tenant} */
    private function tenantMember(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Authorization benchmark', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);

        return [$user, $tenant];
    }
}
