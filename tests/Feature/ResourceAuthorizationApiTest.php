<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationGroupService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use App\Services\Authorization\Resource\AuthorizedPersonalWorkspaceFolderQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResourceAuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_resource_decisions_in_the_submitted_order_without_exposing_an_unshared_folder(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $shared = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $private = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('personal.folder', $shared->id, AuthorizationScope::Personal),
            'READ',
            $collaborator,
        );

        $this->actingAs($collaborator)
            ->postJson('/api/v1/authorization/resource-checks', [
                'checks' => [
                    ['permissionKey' => 'personal.folder.read', 'resource' => ['type' => 'personal.folder', 'id' => $shared->id]],
                    ['permissionKey' => 'personal.folder.edit', 'resource' => ['type' => 'personal.folder', 'id' => $shared->id]],
                    ['permissionKey' => 'personal.folder.read', 'resource' => ['type' => 'personal.folder', 'id' => $private->id]],
                ],
            ])
            ->assertOk()
            ->assertExactJson([
                'decisions' => [
                    ['allowed' => true, 'reasonCode' => 'RESOURCE_RELATION_APPLIES'],
                    ['allowed' => false, 'reasonCode' => 'RESOURCE_RELATION_REQUIRED'],
                    ['allowed' => false, 'reasonCode' => 'RESOURCE_RELATION_REQUIRED'],
                ],
            ]);
    }

    public function test_it_rejects_an_invalid_batch_payload(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/authorization/resource-checks', ['checks' => []])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_it_returns_non_resource_tenant_checks_with_the_same_individual_decision(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Batch', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $administrator = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $administrator->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $this->actingAs($user)
            ->postJson('/api/v1/authorization/resource-checks', [
                'checks' => [
                    ['permissionKey' => 'tenant.availability.manage', 'tenantId' => $tenant->id],
                    ['permissionKey' => 'tenant.availability.manage'],
                ],
            ])
            ->assertOk()
            ->assertExactJson(['decisions' => [
                ['allowed' => true, 'reasonCode' => 'GRANT_APPLIES'],
                ['allowed' => false, 'reasonCode' => 'TENANT_CONTEXT_REQUIRED'],
            ]]);
    }

    public function test_a_tenant_administrator_can_access_every_workspace_folder_unless_a_restriction_applies(): void
    {
        $administrator = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $administrator->id, 'state' => 'ACTIVE']);
        $folder = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Finance', 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $resource = new ResourceReference('tenant.folder', $folder->id, AuthorizationScope::Tenant, $tenant->id);
        $authorization = app(AuthorizationService::class);

        $this->assertSame('TENANT_WORKSPACE_ADMINISTRATOR_APPLIES', $authorization->check($administrator, 'tenant.folder.read', AuthorizationScope::Tenant, $tenant->id, $resource)->reasonCode);
        $this->assertTrue($authorization->check($administrator, 'tenant.folder.edit', AuthorizationScope::Tenant, $tenant->id, $resource)->allowed);

        AuthorizationRestriction::query()->create([
            'idPermission' => AuthorizationPermission::query()->where('key', 'tenant.folder.read')->firstOrFail()->id,
            'idUser' => $administrator->id,
            'idTenant' => $tenant->id,
            'scope' => 'TENANT',
            'active' => true,
        ]);

        $this->assertSame('RESTRICTION_APPLIES', $authorization->check($administrator, 'tenant.folder.read', AuthorizationScope::Tenant, $tenant->id, $resource)->reasonCode);
    }

    public function test_tenant_folder_relations_are_inherited_and_never_grant_another_tenant_workspace(): void
    {
        $member = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Globex', 'state' => 'ACTIVE']);
        TenantMembership::query()->insert([
            ['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE'],
            ['idTenant' => $otherTenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE'],
        ]);
        $root = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Projects', 'state' => 'ACTIVE']);
        $descendant = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'idParentFolder' => $root->id, 'displayName' => 'Roadmap', 'state' => 'ACTIVE']);
        $otherFolder = WorkspaceFolder::query()->create(['idTenant' => $otherTenant->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $relations = app(AuthorizationResourceRelationService::class);
        $relations->create(new ResourceReference('tenant.folder', $root->id, AuthorizationScope::Tenant, $tenant->id), 'READ', $member);
        $group = app(AuthorizationGroupService::class)->create('Editors', AuthorizationScope::Tenant, $tenant->id);
        app(AuthorizationGroupService::class)->addUser($group, $member);
        $editRelation = $relations->create(new ResourceReference('tenant.folder', $root->id, AuthorizationScope::Tenant, $tenant->id), 'EDIT', group: $group);
        $authorization = app(AuthorizationService::class);

        $this->assertTrue($authorization->check($member, 'tenant.folder.read', AuthorizationScope::Tenant, $tenant->id, new ResourceReference('tenant.folder', $descendant->id, AuthorizationScope::Tenant, $tenant->id))->allowed);
        $this->assertTrue($authorization->check($member, 'tenant.folder.edit', AuthorizationScope::Tenant, $tenant->id, new ResourceReference('tenant.folder', $descendant->id, AuthorizationScope::Tenant, $tenant->id))->allowed);
        $this->assertFalse($authorization->check($member, 'tenant.folder.read', AuthorizationScope::Tenant, $otherTenant->id, new ResourceReference('tenant.folder', $otherFolder->id, AuthorizationScope::Tenant, $otherTenant->id))->allowed);

        $relations->deactivate($editRelation);
        $this->assertFalse($authorization->check($member, 'tenant.folder.edit', AuthorizationScope::Tenant, $tenant->id, new ResourceReference('tenant.folder', $descendant->id, AuthorizationScope::Tenant, $tenant->id))->allowed);
    }

    public function test_it_lists_only_the_personal_workspace_and_shared_folder_tree_in_one_authorized_query(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $shared = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $descendant = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'idParentFolder' => $shared->id, 'displayName' => 'Contracts', 'state' => 'ACTIVE']);
        $private = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $own = WorkspaceFolder::query()->create(['idUser' => $collaborator->id, 'displayName' => 'Mine', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('personal.folder', $shared->id, AuthorizationScope::Personal),
            'READ',
            $collaborator,
        );

        $this->actingAs($collaborator)
            ->getJson('/api/v1/authorization/personal-workspace/folders?perPage=100')
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('perPage', 100)
            ->assertJsonCount(3, 'folders')
            ->assertJsonFragment(['id' => $shared->id, 'displayName' => 'Shared'])
            ->assertJsonFragment(['id' => $descendant->id, 'displayName' => 'Contracts'])
            ->assertJsonFragment(['id' => $own->id, 'displayName' => 'Mine'])
            ->assertJsonMissing(['id' => $private->id, 'displayName' => 'Private']);
    }

    public function test_a_personal_read_restriction_denies_the_aggregate_folder_listing(): void
    {
        $user = User::factory()->create();
        WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'personal.folder.read')->firstOrFail();
        AuthorizationRestriction::query()->create([
            'idPermission' => $permission->id,
            'idUser' => $user->id,
            'scope' => 'PERSONAL',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/authorization/personal-workspace/folders')
            ->assertOk()
            ->assertJsonPath('folders', []);
    }

    public function test_the_aggregate_listing_executes_one_database_query_for_many_candidates(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 30) as $number) {
            WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => "Folder {$number}", 'state' => 'ACTIVE']);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();

        $folders = app(AuthorizedPersonalWorkspaceFolderQuery::class)->pageFor($user, 1, 50);

        $this->assertCount(30, $folders);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_pagination_never_exposes_an_unshared_folder_and_matches_reference_decisions(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $shared = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $descendant = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'idParentFolder' => $shared->id, 'displayName' => 'Contracts', 'state' => 'ACTIVE']);
        $private = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $own = WorkspaceFolder::query()->create(['idUser' => $collaborator->id, 'displayName' => 'Mine', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $shared->id, AuthorizationScope::Personal), 'READ', $collaborator);

        $firstPage = $this->actingAs($collaborator)
            ->getJson('/api/v1/authorization/personal-workspace/folders?page=1&perPage=1')
            ->assertOk()
            ->json('folders');
        $secondPage = $this->actingAs($collaborator)
            ->getJson('/api/v1/authorization/personal-workspace/folders?page=2&perPage=1')
            ->assertOk()
            ->json('folders');
        $thirdPage = $this->actingAs($collaborator)
            ->getJson('/api/v1/authorization/personal-workspace/folders?page=3&perPage=1')
            ->assertOk()
            ->json('folders');

        $this->assertSame([$own->id], array_column($firstPage, 'id'));
        $this->assertSame([$shared->id], array_column($secondPage, 'id'));
        $listedIds = [...array_column($firstPage, 'id'), ...array_column($secondPage, 'id'), ...array_column($thirdPage, 'id')];
        $this->assertSame([$descendant->id], array_column($thirdPage, 'id'));
        $this->assertNotContains($private->id, $listedIds);

        $authorization = app(AuthorizationService::class);
        foreach ([$own, $shared, $descendant, $private] as $folder) {
            $expected = $authorization->check(
                $collaborator,
                'personal.folder.read',
                AuthorizationScope::Personal,
                resource: new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal),
            )->allowed;
            $this->assertSame($expected, in_array($folder->id, $listedIds, true));
        }
    }
}
