<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationAccessRequestService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthorizationAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_independent_administrator_approves_a_temporary_access_and_invalidates_a_cached_denial(): void
    {
        [$tenant, $requester, $approver, $permission] = $this->context();
        $service = app(AuthorizationAccessRequestService::class);
        $request = $service->request($requester, $requester, $permission, AuthorizationScope::Tenant, $tenant->id, now(), now()->addHour());
        $authorization = app(AuthorizationService::class);
        $this->assertFalse($authorization->check($requester, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $service->approve($request, $approver);

        $this->assertTrue($authorization->check($requester, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.access_request.approved', 'targetId' => $request->id]);
    }

    public function test_requester_cannot_approve_their_own_request(): void
    {
        [$tenant, $requester, $approver, $permission] = $this->context();
        $service = app(AuthorizationAccessRequestService::class);
        $request = $service->request($requester, $requester, $permission, AuthorizationScope::Tenant, $tenant->id, now()->subHour(), now()->addMinute());
        $this->expectException(LogicException::class);
        $service->approve($request, $requester);
    }

    public function test_expiration_and_revocation_remove_an_approved_temporary_access(): void
    {
        [$tenant, $requester, $approver, $permission] = $this->context();
        $service = app(AuthorizationAccessRequestService::class);
        $authorization = app(AuthorizationService::class);

        $expired = $service->request($requester, $requester, $permission, AuthorizationScope::Tenant, $tenant->id, now()->subHour(), now()->addMinute());
        $service->approve($expired, $approver);
        $this->assertTrue($authorization->check($requester, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $expired->update(['endsAt' => now()]);
        $this->assertSame(1, $service->expireDue());
        $this->assertDatabaseHas('auth_access_request', ['id' => $expired->id, 'state' => 'EXPIRED']);
        $this->assertFalse($authorization->check($requester, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $revoked = $service->request($requester, $requester, $permission, AuthorizationScope::Tenant, $tenant->id, now(), now()->addHour());
        $service->approve($revoked, $approver);
        $this->assertTrue($authorization->check($requester, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $service->revoke($revoked, $requester);
        $this->assertDatabaseHas('auth_access_request', ['id' => $revoked->id, 'state' => 'REVOKED']);
        $this->assertFalse($authorization->check($requester, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
    }

    private function context(): array
    {
        $tenant = Tenant::query()->create(['displayName' => 'Temporary access', 'state' => 'ACTIVE']);
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        foreach ([$requester, $approver] as $user) {
            TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        }
        $administrator = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $administrator->id, 'idUser' => $approver->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->create(['key' => 'tenant.temporary.export', 'displayName' => 'Temporary export', 'description' => 'Temporary export.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);

        return [$tenant, $requester, $approver, $permission];
    }
}
