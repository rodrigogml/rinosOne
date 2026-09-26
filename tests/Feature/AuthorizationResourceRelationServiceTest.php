<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationAuditEvent;
use App\Models\AuthorizationGroup;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuthorizationResourceRelationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_deactivates_and_removes_an_audited_personal_folder_relation(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $resource = new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal);
        $service = app(AuthorizationResourceRelationService::class);

        $relation = $service->create($resource, 'READ', $collaborator, actorUserId: $owner->id, correlationId: 'share-1');

        $this->assertTrue($relation->active);
        $this->assertDatabaseHas('auth_resource_relation', ['id' => $relation->id, 'idUser' => $collaborator->id, 'relationKey' => 'READ', 'active' => true]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.resource_relation.activated', 'targetId' => $relation->id, 'correlationId' => 'share-1']);

        $service->deactivate($relation, $owner->id);
        $this->assertFalse($relation->fresh()->active);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.resource_relation.deactivated', 'targetId' => $relation->id]);

        $service->remove($relation, $owner->id);
        $this->assertDatabaseMissing('auth_resource_relation', ['id' => $relation->id]);
        $this->assertSame(3, AuthorizationAuditEvent::query()->count());
    }

    public function test_it_rejects_an_unsupported_relation_without_persisting_it(): void
    {
        $owner = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not supported');
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal),
            'ADMIN',
            User::factory()->create(),
        );
    }

    public function test_a_folder_relation_applies_to_descendants_but_not_siblings_or_another_action(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $parent = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'idParentFolder' => $parent->id, 'displayName' => 'Invoices', 'state' => 'ACTIVE']);
        $sibling = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $service = app(AuthorizationResourceRelationService::class);
        $parentResource = new ResourceReference('personal.folder', $parent->id, AuthorizationScope::Personal);
        $service->create($parentResource, 'READ', $collaborator);
        $authorization = app(AuthorizationService::class);

        $this->assertTrue($authorization->check($collaborator, 'personal.folder.read', AuthorizationScope::Personal, resource: new ResourceReference('personal.folder', $child->id, AuthorizationScope::Personal))->allowed);
        $this->assertFalse($authorization->check($collaborator, 'personal.folder.edit', AuthorizationScope::Personal, resource: new ResourceReference('personal.folder', $child->id, AuthorizationScope::Personal))->allowed);
        $this->assertFalse($authorization->check($collaborator, 'personal.folder.read', AuthorizationScope::Personal, resource: new ResourceReference('personal.folder', $sibling->id, AuthorizationScope::Personal))->allowed);
    }

    public function test_folder_read_and_edit_relations_are_inherited_and_revocation_removes_access_from_descendants(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $parent = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'idParentFolder' => $parent->id, 'displayName' => 'Contracts', 'state' => 'ACTIVE']);
        $resource = new ResourceReference('personal.folder', $parent->id, AuthorizationScope::Personal);
        $childResource = new ResourceReference('personal.folder', $child->id, AuthorizationScope::Personal);
        $relations = app(AuthorizationResourceRelationService::class);
        $authorization = app(AuthorizationService::class);

        $read = $relations->create($resource, 'READ', $collaborator);
        $edit = $relations->create($resource, 'EDIT', $collaborator);

        $this->assertTrue($authorization->check($collaborator, 'personal.folder.read', AuthorizationScope::Personal, resource: $childResource)->allowed);
        $this->assertTrue($authorization->check($collaborator, 'personal.folder.edit', AuthorizationScope::Personal, resource: $childResource)->allowed);

        $relations->deactivate($read, $owner->id);
        $this->assertFalse($authorization->check($collaborator, 'personal.folder.read', AuthorizationScope::Personal, resource: $childResource)->allowed);
        $this->assertTrue($authorization->check($collaborator, 'personal.folder.edit', AuthorizationScope::Personal, resource: $childResource)->allowed);

        $relations->deactivate($edit, $owner->id);
        $this->assertFalse($authorization->check($collaborator, 'personal.folder.edit', AuthorizationScope::Personal, resource: $childResource)->allowed);
    }

    public function test_a_relation_assigned_to_a_personal_group_applies_to_its_member(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $group = AuthorizationGroup::query()->create(['displayName' => 'Collaborators', 'scope' => AuthorizationScope::Personal->value, 'active' => true]);
        DB::table('auth_group_user')->insert(['idGroup' => $group->id, 'idUser' => $collaborator->id]);
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal),
            'EDIT',
            group: $group,
        );

        $decision = app(AuthorizationService::class)->check(
            $collaborator,
            'personal.folder.edit',
            AuthorizationScope::Personal,
            resource: new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal),
        );

        $this->assertTrue($decision->allowed);
        $this->assertSame('RESOURCE_RELATION_APPLIES', $decision->reasonCode);
    }
}
