<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessConfigurationTest extends TestCase
{
    public function test_the_access_policy_has_safe_defaults_and_reserved_schema_names(): void
    {
        $this->assertSame('rinosone', config('access.schemas.core'));
        $this->assertSame('rinosone_', config('access.schemas.tenantPrefix'));
        $this->assertSame(3, config('access.tenantProvisioning.maximumAttempts'));
        $this->assertSame([1, 5, 15], config('access.tenantProvisioning.retryDelaysMinutes'));
        $this->assertSame(10, config('access.authentication.emailChallengeLifetimeMinutes'));
        $this->assertSame(3, config('access.authentication.emailEmissionLimit'));
        $this->assertSame(5, config('access.authentication.codeAttemptLimit'));
        $this->assertSame(3, config('access.authentication.codeMaximumAttempts'));
        $this->assertSame(0, config('access.authentication.sessionInactivityTimeoutMinutes'));
        $this->assertSame(0, config('access.authentication.persistentLoginLifetimeDays'));
        $this->assertSame(30, config('access.authentication.securityLogRetentionDays'));
    }
}
