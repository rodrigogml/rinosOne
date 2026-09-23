<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Access\AccessRateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessRateLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_emission_limit_applies_a_configurable_temporary_block(): void
    {
        config([
            'access.authentication.emailEmissionLimit' => 1,
            'access.authentication.originEmissionLimit' => 10,
            'access.authentication.userEmissionLimit' => 10,
            'access.authentication.temporaryBlockMinutes' => 15,
        ]);

        $service = app(AccessRateLimitService::class);
        $first = $service->attemptEmailEmission('visitor@example.test', '203.0.113.4');
        $second = $service->attemptEmailEmission('visitor@example.test', '203.0.113.4');

        $this->assertTrue($first->allowed);
        $this->assertFalse($second->allowed);
        $this->assertSame(900, $second->retryAfterSeconds);
        $this->assertStringNotContainsString(
            'visitor@example.test',
            $this->app['db']->table('cache')->pluck('key')->implode(' '),
        );
    }

    public function test_origin_and_identifiable_user_receive_independent_limits(): void
    {
        config([
            'access.authentication.emailEmissionLimit' => 10,
            'access.authentication.originEmissionLimit' => 1,
            'access.authentication.userEmissionLimit' => 1,
        ]);

        $service = app(AccessRateLimitService::class);
        $user = User::factory()->create();

        $this->assertTrue($service->attemptEmailEmission('one@example.test', '203.0.113.4')->allowed);
        $this->assertFalse($service->attemptEmailEmission('two@example.test', '203.0.113.4')->allowed);
        $this->assertTrue($service->attemptEmailEmission('three@example.test', '203.0.113.5', $user->id)->allowed);
        $this->assertFalse($service->attemptEmailEmission('four@example.test', '203.0.113.6', $user->id)->allowed);
    }

    public function test_password_limit_uses_a_neutral_decision_for_email_and_origin(): void
    {
        config([
            'access.authentication.passwordAttemptLimit' => 1,
            'access.authentication.userPasswordAttemptLimit' => 10,
            'access.authentication.temporaryBlockMinutes' => 15,
        ]);

        $service = app(AccessRateLimitService::class);

        $this->assertTrue($service->attemptPassword('account@example.test', '203.0.113.4')->allowed);
        $denied = $service->attemptPassword('account@example.test', '203.0.113.4');

        $this->assertFalse($denied->allowed);
        $this->assertSame(900, $denied->retryAfterSeconds);
    }
}
