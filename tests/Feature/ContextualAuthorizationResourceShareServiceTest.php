<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationCapabilities;
use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use App\Services\Authorization\Administration\ContextualAuthorizationResourceShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ContextualAuthorizationResourceShareServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_direct_and_inherited_personal_folder_relations_without_item_ownership(): void
    {
        $responsible = User::factory()->create();
        $recipient = User::factory()->create();
        $parent = WorkspaceFolder::query()->create(['idUser' => $responsible->id, 'idTenant' => null, 'displayName' => 'Projetos', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idUser' => $responsible->id, 'idTenant' => null, 'idParentFolder' => $parent->id, 'displayName' => '2026', 'state' => 'ACTIVE']);
        $context = new AuthorizationAdministrationContext(AuthorizationScope::Personal, null, new AuthorizationAdministrationCapabilities(true, false, true, false));
        $shares = app(ContextualAuthorizationResourceShareService::class);

        $inherited = $shares->create($responsible, $context, $parent->id, $recipient->id, 'READ');
        $this->assertSame('DIRECT', $inherited->origin);
        $this->assertSame('USER', $inherited->grantee->subjectType);
        $this->assertSame('INHERITED', $shares->shares($context, $child->id)[0]->origin);
        $this->assertSame($parent->id, $shares->shares($context, $child->id)[0]->inheritedFrom['resourceId']);

        $direct = $shares->create($responsible, $context, $child->id, $recipient->id, 'EDIT');
        $updated = $shares->update($responsible, $context, $child->id, $direct->id, 'READ');
        $this->assertSame('READ', $updated->relation);
        try {
            $shares->update($responsible, $context, $child->id, $direct->id, 'INVALID');
            $this->fail('An unsupported relation must not be persisted.');
        } catch (LogicException) {
            $this->assertSame('READ', collect($shares->shares($context, $child->id))->first(fn ($share): bool => $share->id === $direct->id)->relation);
        }
        $shares->revoke($responsible, $context, $child->id, $direct->id);
        $this->assertCount(1, $shares->shares($context, $child->id));
    }
}
