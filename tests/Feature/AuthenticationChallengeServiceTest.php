<?php

namespace Tests\Feature;

use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Models\User;
use App\Services\Access\AuthenticationChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationChallengeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_issue_persists_only_hashes_for_a_link_token_and_a_six_digit_code(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');

        $issued = app(AuthenticationChallengeService::class)->issue(
            User::factory()->create(),
            AuthenticationChallengePurpose::EmailVerification,
            true,
        );

        $stored = $this->app['db']->table('authenticationChallenge')->find($issued->challengeId);

        $this->assertSame('2026-09-22 12:10:00', $issued->expiresAt->format('Y-m-d H:i:s'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $issued->code);
        $this->assertNotSame($issued->linkToken, $stored->secretHash);
        $this->assertTrue(Hash::check($issued->linkToken, $stored->secretHash));
        $this->assertNotSame($issued->code, $stored->codeHash);
        $this->assertTrue(Hash::check($issued->code, $stored->codeHash));
        $this->assertTrue((bool) $stored->rememberMeRequested);
    }

    public function test_new_challenge_replaces_only_the_same_users_same_purpose(): void
    {
        $service = app(AuthenticationChallengeService::class);
        $user = User::factory()->create();
        $first = $service->issue($user, AuthenticationChallengePurpose::EmailVerification);
        $otherPurpose = $service->issue($user, AuthenticationChallengePurpose::PasswordlessLogin);
        $replacement = $service->issue($user, AuthenticationChallengePurpose::EmailVerification);

        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $first->challengeId]);
        $this->assertDatabaseHas('authenticationChallenge', ['id' => $otherPurpose->challengeId]);
        $this->assertDatabaseHas('authenticationChallenge', ['id' => $replacement->challengeId]);
    }

    public function test_link_and_code_share_a_single_exclusive_consumption(): void
    {
        $service = app(AuthenticationChallengeService::class);
        $issued = $service->issue(
            User::factory()->create(),
            AuthenticationChallengePurpose::PasswordlessLogin,
            true,
        );

        $consumed = $service->consumeByCode($issued->challengeId, $issued->code);

        $this->assertNotNull($consumed);
        $this->assertSame(AuthenticationChallengePurpose::PasswordlessLogin, $consumed->purpose);
        $this->assertTrue($consumed->rememberMeRequested);
        $this->assertNull($service->consumeByLinkToken($issued->challengeId, $issued->linkToken));
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $issued->challengeId]);
    }

    public function test_expired_challenge_is_rejected_and_deleted_on_verification(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $service = app(AuthenticationChallengeService::class);
        $issued = $service->issue(User::factory()->create(), AuthenticationChallengePurpose::EmailVerification);

        Carbon::setTestNow('2026-09-22 12:10:00');

        $this->assertNull($service->consumeByLinkToken($issued->challengeId, $issued->linkToken));
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $issued->challengeId]);
    }

    public function test_three_invalid_code_attempts_discard_the_emission(): void
    {
        config(['access.authentication.codeMaximumAttempts' => 3]);
        $service = app(AuthenticationChallengeService::class);
        $issued = $service->issue(User::factory()->create(), AuthenticationChallengePurpose::EmailVerification);
        $wrongCodes = collect(range(0, 3))
            ->map(fn (int $candidate): string => str_pad((string) $candidate, 6, '0', STR_PAD_LEFT))
            ->reject(fn (string $candidate): bool => $candidate === $issued->code)
            ->take(3)
            ->values();

        $this->assertNull($service->consumeByCode($issued->challengeId, $wrongCodes[0], '203.0.113.4'));
        $this->assertDatabaseHas('authenticationChallenge', ['id' => $issued->challengeId, 'failedAttempts' => 1]);
        $this->assertNull($service->consumeByCode($issued->challengeId, $wrongCodes[1], '203.0.113.4'));
        $this->assertDatabaseHas('authenticationChallenge', ['id' => $issued->challengeId, 'failedAttempts' => 2]);
        $this->assertNull($service->consumeByCode($issued->challengeId, $wrongCodes[2], '203.0.113.4'));
        $this->assertNull($service->consumeByCode($issued->challengeId, $issued->code, '203.0.113.4'));
        $this->assertNull($service->consumeByLinkToken($issued->challengeId, $issued->linkToken));
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $issued->challengeId]);
    }
}
