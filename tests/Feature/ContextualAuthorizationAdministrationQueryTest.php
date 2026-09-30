<?php

namespace Tests\Feature;

use App\Domain\Authorization\Administration\AuthorizationAdministrationSubjectNotAvailableException;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationAuditEvent;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\AuthorizationServiceIdentity;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationCapabilities;
use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use App\Services\Authorization\Administration\ContextualAuthorizationAdministrationQuery;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContextualAuthorizationAdministrationQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_only_active_subjects_and_context_catalogs_for_a_tenant(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        $actor = User::factory()->create(['displayName' => 'Administradora']);
        $member = User::factory()->create(['displayName' => 'Membro']);
        $inactive = User::factory()->create(['displayName' => 'Inativo']);
        foreach ([$actor, $member] as $user) {
            TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        }
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $inactive->id, 'state' => 'INACTIVE']);
        $identity = AuthorizationServiceIdentity::query()->create(['idOwnerUser' => $actor->id, 'idTenant' => $tenant->id, 'displayName' => 'Integração fiscal', 'purpose' => 'Integração', 'scope' => 'TENANT', 'state' => 'ACTIVE']);

        $query = app(ContextualAuthorizationAdministrationQuery::class);
        $context = $this->tenantContext($tenant->id);
        $subjects = $query->subjects($actor, $context, null);

        $this->assertEqualsCanonicalizing(['Administradora', 'Integração fiscal', 'Membro'], $subjects->getCollection()->pluck('displayName')->all());
        $this->assertFalse($subjects->getCollection()->contains(fn ($subject): bool => $subject->displayName === 'Inativo'));
        $this->assertSame('SERVICE_IDENTITY', $subjects->getCollection()->firstWhere('displayName', 'Integração fiscal')->subjectType);
        $this->assertSame(3, $query->subjects($actor, $context, null, 1, 1)->total());
        $this->assertCount(1, $query->subjects($actor, $context, null, 1, 1)->items());
        $this->assertNotEmpty($query->roles($context, null)->items());
        $this->assertNotEmpty($query->permissions($context, null));
        $group = AuthorizationGroup::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Financeiro', 'scope' => 'TENANT', 'active' => true]);
        $this->assertSame($group->id, $query->groups($context, 'financeiro')->items()[0]->id);
        $this->assertSame('GROUP', $query->groups($context, 'financeiro')->items()[0]->catalogType);
        $this->assertSame($identity->id, $query->subjects($actor, $context, 'fiscal')->items()[0]->subjectId);

        $administrator = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $administrator->id, 'idUser' => $actor->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $this->assertContains('tenant.availability.manage', $query->effectiveAccess($actor, $context, 'USER', $actor->id)['effectiveCapabilities']);
    }

    public function test_it_distinguishes_colliding_user_and_service_identity_bigint_identifiers(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        $actor = User::factory()->create(['displayName' => 'Administradora']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $actor->id, 'state' => 'ACTIVE']);
        $identity = AuthorizationServiceIdentity::query()->create(['idOwnerUser' => $actor->id, 'idTenant' => $tenant->id, 'displayName' => 'Integração', 'purpose' => 'Integração', 'scope' => 'TENANT', 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('scope', 'TENANT')->firstOrFail();
        DB::table('auth_service_identity_permission')->insert(['idServiceIdentity' => $identity->id, 'idPermission' => $permission->id]);

        $access = app(ContextualAuthorizationAdministrationQuery::class)->effectiveAccess($actor, $this->tenantContext($tenant->id), 'SERVICE_IDENTITY', $identity->id);

        $this->assertSame('SERVICE_IDENTITY', $access['subject']->subjectType);
        $this->assertContains($permission->key, $access['effectiveCapabilities']);
    }

    public function test_it_does_not_expose_subjects_from_another_tenant(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Outra', 'state' => 'ACTIVE']);
        $actor = User::factory()->create();
        $otherMember = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $actor->id, 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $otherTenant->id, 'idUser' => $otherMember->id, 'state' => 'ACTIVE']);

        $this->expectException(AuthorizationAdministrationSubjectNotAvailableException::class);
        app(ContextualAuthorizationAdministrationQuery::class)->effectiveAccess($actor, $this->tenantContext($tenant->id), 'USER', $otherMember->id);
    }

    public function test_it_projects_a_tenant_folder_share_with_an_explicit_resource_reference(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        $administrator = User::factory()->create();
        $member = User::factory()->create();
        foreach ([$administrator, $member] as $user) {
            TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        }
        $folder = WorkspaceFolder::query()->create(['idUser' => null, 'idTenant' => $tenant->id, 'displayName' => 'Fiscal', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('tenant.folder', $folder->id, AuthorizationScope::Tenant, $tenant->id),
            'READ',
            $member,
        );

        $access = app(ContextualAuthorizationAdministrationQuery::class)->effectiveAccess($administrator, $this->tenantContext($tenant->id), 'USER', $member->id);

        $this->assertSame('SHARE', $access['subject']->accessSources[0]->type);
        $this->assertSame(['resourceType' => 'FOLDER', 'resourceId' => $folder->id], $access['subject']->accessSources[0]->resource);
    }

    public function test_personal_and_platform_subject_lists_do_not_enumerate_other_users(): void
    {
        $actor = User::factory()->create(['displayName' => 'Solicitante']);
        User::factory()->create(['displayName' => 'Outra pessoa']);
        $query = app(ContextualAuthorizationAdministrationQuery::class);

        $personal = new AuthorizationAdministrationContext(AuthorizationScope::Personal, null, new AuthorizationAdministrationCapabilities(true, false, true, false));
        $platform = new AuthorizationAdministrationContext(AuthorizationScope::Platform, null, new AuthorizationAdministrationCapabilities(true, false, false, false));

        $this->assertSame([$actor->id], $query->subjects($actor, $personal, null)->getCollection()->where('subjectType', 'USER')->pluck('subjectId')->all());
        $this->assertSame([$actor->id], $query->subjects($actor, $platform, null)->getCollection()->where('subjectType', 'USER')->pluck('subjectId')->all());
    }

    public function test_it_filters_tenant_audit_events_without_exposing_snapshots_or_other_tenants(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Outra', 'state' => 'ACTIVE']);
        $actor = User::factory()->create();
        foreach ([$tenant, $otherTenant] as $item) {
            AuthorizationAuditEvent::query()->create(['occurredAt' => now(), 'idActorUser' => $actor->id, 'idTenant' => $item->id, 'operation' => 'ROLE_ASSIGNED', 'targetType' => 'ROLE', 'targetId' => 1]);
        }

        $events = app(ContextualAuthorizationAdministrationQuery::class)->auditEvents($actor, $this->tenantContext($tenant->id), 'ROLE_ASSIGNED', null, null, null, null);

        $this->assertCount(1, $events->items());
        $this->assertSame($actor->id, $events->items()[0]->actorUserId);
        $this->assertFalse(property_exists($events->items()[0], 'before'));
    }

    private function tenantContext(int $tenantId): AuthorizationAdministrationContext
    {
        return new AuthorizationAdministrationContext(AuthorizationScope::Tenant, $tenantId, new AuthorizationAdministrationCapabilities(true, true, true, true));
    }
}
