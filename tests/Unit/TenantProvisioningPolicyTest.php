<?php

namespace Tests\Unit;

use App\Domain\Tenant\Provisioning\TenantProvisioningPolicy;
use Tests\TestCase;

class TenantProvisioningPolicyTest extends TestCase
{
    public function test_it_uses_the_approved_default_retry_policy(): void
    {
        $policy = app(TenantProvisioningPolicy::class);

        $this->assertSame(3, $policy->maximumAttempts());
        $this->assertSame(60, $policy->retryDelaySeconds(1));
        $this->assertSame(300, $policy->retryDelaySeconds(2));
        $this->assertSame(900, $policy->retryDelaySeconds(3));
        $this->assertNull($policy->retryDelaySeconds(4));
    }
}
