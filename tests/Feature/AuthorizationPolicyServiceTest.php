<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationPolicyService;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_audits_binds_and_invalidates_a_tenant_policy(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Policy administration', 'state' => 'ACTIVE']);
        $actor = User::factory()->create();
        $service = app(AuthorizationPolicyService::class);
        $policy = $service->publish('amount-limit', AuthorizationScope::Tenant, $tenant->id, ['all' => [['type' => 'AMOUNT_MAXIMUM', 'maximum' => 100]]], $actor->id, 'policy-1');
        $binding = $service->bind($policy, AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail(), $actor->id, 'policy-1');

        $this->assertSame(1, $policy->version);
        $this->assertTrue($binding->active);
        $this->assertSame(3, app(PolicyVersionService::class)->current(AuthorizationScope::Tenant, $tenant->id));
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.policy.published', 'targetId' => $policy->id, 'correlationId' => 'policy-1']);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.policy.bound', 'targetId' => $binding->id, 'correlationId' => 'policy-1']);
    }
}
