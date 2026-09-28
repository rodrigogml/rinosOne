<?php

namespace Tests\Feature;

use App\Models\AuthorizationPermission;
use App\Models\AuthorizationPolicy;
use App\Models\AuthorizationPolicyBinding;
use App\Models\AuthorizationRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAdvancedPolicyPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_versioned_policy_and_its_permission_binding_use_the_same_tenant_context(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Advanced authorization', 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        $policy = AuthorizationPolicy::query()->create([
            'idTenant' => $tenant->id,
            'scope' => 'TENANT',
            'key' => 'tenant.availability.maximum-amount',
            'contextFingerprint' => "tenant:{$tenant->id}",
            'type' => 'AMOUNT_MAXIMUM',
            'version' => 1,
            'definition' => ['maximum' => 10000],
            'active' => true,
        ]);
        $binding = AuthorizationPolicyBinding::query()->create([
            'idPolicy' => $policy->id,
            'idPermission' => $permission->id,
            'idRole' => $role->id,
            'active' => true,
        ]);

        $this->assertSame(['maximum' => 10000], $policy->fresh()->definition);
        $this->assertTrue($binding->fresh()->active);
        $this->assertDatabaseHas('auth_policy_binding', ['idPolicy' => $policy->id, 'idPermission' => $permission->id, 'idRole' => $role->id]);
    }
}
