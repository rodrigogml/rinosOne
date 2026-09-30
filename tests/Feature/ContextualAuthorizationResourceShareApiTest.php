<?php

namespace Tests\Feature;

use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualAuthorizationResourceShareApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_folder_shares_can_be_created_changed_and_revoked_but_inherited_shares_cannot_be_changed_on_a_child(): void
    {
        $responsible = User::factory()->create();
        $recipient = User::factory()->create();
        $parent = WorkspaceFolder::query()->create(['idUser' => $responsible->id, 'idTenant' => null, 'displayName' => 'Projetos', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idUser' => $responsible->id, 'idTenant' => null, 'idParentFolder' => $parent->id, 'displayName' => '2026', 'state' => 'ACTIVE']);

        $share = $this->actingAs($responsible)->postJson("/api/v1/authorization/personal/resources/FOLDER/{$parent->id}/shares", ['subjectId' => $recipient->id, 'relation' => 'READ'])
            ->assertCreated()
            ->assertJsonPath('share.origin', 'DIRECT')
            ->json('share');
        $this->actingAs($responsible)->getJson("/api/v1/authorization/personal/resources/FOLDER/{$child->id}/shares")
            ->assertOk()
            ->assertJsonPath('workspaceResponsible.id', $responsible->id)
            ->assertJsonPath('shares.0.origin', 'INHERITED')
            ->assertJsonPath('shares.0.inheritedFrom.resourceId', $parent->id);
        $this->actingAs($responsible)->patchJson("/api/v1/authorization/personal/resources/FOLDER/{$child->id}/shares/{$share['id']}", ['relation' => 'EDIT'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'AUTHORIZATION_RESOURCE_SHARE_NOT_DIRECT');
        $this->actingAs($responsible)->patchJson("/api/v1/authorization/personal/resources/FOLDER/{$parent->id}/shares/{$share['id']}", ['relation' => 'EDIT'])
            ->assertOk()
            ->assertJsonPath('share.relation', 'EDIT');
        $this->actingAs($responsible)->deleteJson("/api/v1/authorization/personal/resources/FOLDER/{$parent->id}/shares/{$share['id']}")
            ->assertNoContent();
        $this->actingAs($responsible)->getJson("/api/v1/authorization/personal/resources/FOLDER/{$parent->id}/shares")
            ->assertOk()
            ->assertJsonCount(0, 'shares');
    }

    public function test_tenant_share_never_accepts_a_folder_from_another_tenant(): void
    {
        $administrator = User::factory()->create();
        $recipient = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Outra', 'state' => 'ACTIVE']);
        foreach ([$administrator, $recipient] as $user) {
            TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        }
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $folder = WorkspaceFolder::query()->create(['idUser' => null, 'idTenant' => $tenant->id, 'displayName' => 'Financeiro', 'state' => 'ACTIVE']);
        $otherFolder = WorkspaceFolder::query()->create(['idUser' => null, 'idTenant' => $otherTenant->id, 'displayName' => 'Isolada', 'state' => 'ACTIVE']);

        $this->actingAs($administrator)->postJson("/api/v1/tenants/{$tenant->id}/authorization/resources/FOLDER/{$folder->id}/shares", ['subjectId' => $recipient->id, 'relation' => 'READ'])
            ->assertCreated()
            ->assertJsonPath('share.resourceId', $folder->id);
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/resources/FOLDER/{$otherFolder->id}/shares")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE');
    }

    public function test_recipient_search_requires_a_term_and_keeps_tenant_membership_boundary(): void
    {
        $administrator = User::factory()->create(['displayName' => 'Administradora']);
        $member = User::factory()->create(['displayName' => 'Ana membro']);
        $outsider = User::factory()->create(['displayName' => 'Ana externa']);
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        foreach ([$administrator, $member] as $user) {
            TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        }
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/share-recipients?query=A")
            ->assertOk()
            ->assertJsonCount(0, 'recipients');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/share-recipients?query=Ana")
            ->assertOk()
            ->assertJsonPath('recipients.0.subjectId', $member->id)
            ->assertJsonMissing(['subjectId' => $outsider->id]);
    }

    public function test_personal_and_tenant_share_routes_cannot_cross_workspace_boundaries(): void
    {
        $user = User::factory()->create(['displayName' => 'Responsável pessoal']);
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $personalFolder = WorkspaceFolder::query()->create(['idUser' => $user->id, 'idTenant' => null, 'displayName' => 'Pessoal', 'state' => 'ACTIVE']);
        $tenantFolder = WorkspaceFolder::query()->create(['idUser' => null, 'idTenant' => $tenant->id, 'displayName' => 'Organização', 'state' => 'ACTIVE']);

        $this->actingAs($user)->getJson("/api/v1/authorization/personal/resources/FOLDER/{$personalFolder->id}/shares")
            ->assertOk()
            ->assertJsonPath('workspaceResponsible.type', 'USER')
            ->assertJsonPath('workspaceResponsible.id', $user->id);
        $this->actingAs($user)->getJson("/api/v1/tenants/{$tenant->id}/authorization/resources/FOLDER/{$tenantFolder->id}/shares")
            ->assertOk()
            ->assertJsonPath('workspaceResponsible.type', 'TENANT')
            ->assertJsonPath('workspaceResponsible.id', $tenant->id);
        $this->actingAs($user)->getJson("/api/v1/authorization/personal/resources/FOLDER/{$tenantFolder->id}/shares")
            ->assertNotFound();
        $this->actingAs($user)->getJson("/api/v1/tenants/{$tenant->id}/authorization/resources/FOLDER/{$personalFolder->id}/shares")
            ->assertNotFound();
    }
}
