<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthorizationConfigurationTest extends TestCase
{
    public function test_authorization_audit_retention_defaults_to_ninety_days(): void
    {
        $this->assertSame(90, config('authorization.auditRetentionDays'));
    }

    public function test_authorization_audit_retention_can_be_overridden_by_environment_configuration(): void
    {
        config()->set('authorization.auditRetentionDays', 30);

        $this->assertSame(30, config('authorization.auditRetentionDays'));
    }
}
