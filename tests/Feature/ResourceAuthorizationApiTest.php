<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
