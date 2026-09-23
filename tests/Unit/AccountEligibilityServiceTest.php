<?php

namespace Tests\Unit;

use App\Domain\Access\Account\AccountEligibilityService;
use App\Domain\Access\Account\AccountStatus;
use App\Models\User;
use Tests\TestCase;

class AccountEligibilityServiceTest extends TestCase
{
    public function test_unverified_account_is_pending_email_verification(): void
    {
        $user = new User([
            'email' => 'member@example.test',
            'displayName' => 'Member',
        ]);

        $service = new AccountEligibilityService;

        $this->assertSame(AccountStatus::PendingEmailVerification, $service->status($user));
        $this->assertFalse($service->canAuthenticate($user));
    }

    public function test_verified_account_without_display_name_is_pending_display_name(): void
    {
        $user = new User([
            'email' => 'member@example.test',
            'displayName' => '   ',
            'emailVerifiedAt' => now(),
        ]);

        $service = new AccountEligibilityService;

        $this->assertSame(AccountStatus::PendingDisplayName, $service->status($user));
        $this->assertFalse($service->canAuthenticate($user));
    }

    public function test_verified_account_with_display_name_is_active_and_can_authenticate(): void
    {
        $user = new User([
            'email' => 'member@example.test',
            'displayName' => 'Member',
            'emailVerifiedAt' => now(),
        ]);

        $service = new AccountEligibilityService;

        $this->assertSame(AccountStatus::Active, $service->status($user));
        $this->assertTrue($service->canAuthenticate($user));
    }
}
