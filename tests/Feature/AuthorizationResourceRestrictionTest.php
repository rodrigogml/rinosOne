<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationPermission;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationRestrictionService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationResourceRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_resource_restriction_denies_only_the_registered_qualified_resource(): void
    {
        $user = User::factory()->create();
        $restrictedFolder = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Restricted', 'state' => 'ACTIVE']);
        $otherFolder = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Other', 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'personal.folder.read')->firstOrFail();
        $restrictedResource = new ResourceReference('personal.folder', $restrictedFolder->id, AuthorizationScope::Personal);
        $otherResource = new ResourceReference('personal.folder', $otherFolder->id, AuthorizationScope::Personal);

        app(AuthorizationRestrictionService::class)->create($permission, $user, null, AuthorizationScope::Personal, resource: $restrictedResource);

        $authorization = app(AuthorizationService::class);
        $restrictedDecision = $authorization->check($user, $permission->key, AuthorizationScope::Personal, resource: $restrictedResource);
        $otherDecision = $authorization->check($user, $permission->key, AuthorizationScope::Personal, resource: $otherResource);

        $this->assertFalse($restrictedDecision->allowed);
        $this->assertSame('RESTRICTION_APPLIES', $restrictedDecision->reasonCode);
        $this->assertTrue($otherDecision->allowed);
    }
}
