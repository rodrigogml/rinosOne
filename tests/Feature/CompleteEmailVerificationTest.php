<?php

namespace Tests\Feature;

use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Models\User;
use App\Services\Access\AuthenticationChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompleteEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_confirms_email_requires_name_and_authenticates_user(): void
    {
        $user = User::factory()->unverified()->create(['displayName' => 'Visitante']);
        $issued = app(AuthenticationChallengeService::class)->issue($user, AuthenticationChallengePurpose::EmailVerification);

        $this->postJson('/api/v1/auth/email-verifications', ['challengeId' => $issued->challengeId, 'code' => $issued->code])
            ->assertCreated()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.displayName', 'Visitante')->assertJsonMissing(['email']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->emailVerifiedAt);
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $issued->challengeId]);
    }

    public function test_link_confirms_email_once_and_rejects_reuse(): void
    {
        $user = User::factory()->unverified()->create(['displayName' => 'Visitante']);
        $issued = app(AuthenticationChallengeService::class)->issue($user, AuthenticationChallengePurpose::EmailVerification);
        $payload = ['challengeId' => $issued->challengeId, 'token' => $issued->linkToken];

        $this->postJson('/api/v1/auth/email-verifications/link-confirmations', $payload)->assertCreated();
        $this->postJson('/api/v1/auth/email-verifications/link-confirmations', $payload)->assertBadRequest()->assertJsonPath('code', 'INVALID_CREDENTIAL');
    }

    public function test_remember_me_choice_creates_persistent_authentication_for_link_confirmation(): void
    {
        $user = User::factory()->unverified()->create(['displayName' => 'Visitante']);
        $issued = app(AuthenticationChallengeService::class)->issue($user, AuthenticationChallengePurpose::EmailVerification, true);

        $this->postJson('/api/v1/auth/email-verifications/link-confirmations', [
            'challengeId' => $issued->challengeId,
            'token' => $issued->linkToken,
        ])->assertCreated()->assertCookie(config('access.authentication.persistentCookieName'));

        $persistentAuthentication = $this->app['db']->table('persistentAuthentication')->where('idUser', $user->id)->sole();
        $this->assertNotEmpty($persistentAuthentication->secretHash);
        $this->assertNotSame($issued->linkToken, $persistentAuthentication->secretHash);
    }

    public function test_expired_code_returns_neutral_invalid_credential(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');
        $user = User::factory()->unverified()->create(['displayName' => 'Visitante']);
        $issued = app(AuthenticationChallengeService::class)->issue($user, AuthenticationChallengePurpose::EmailVerification);
        Carbon::setTestNow('2026-09-23 12:10:00');

        $this->postJson('/api/v1/auth/email-verifications', ['challengeId' => $issued->challengeId, 'code' => $issued->code])
            ->assertBadRequest()->assertJsonPath('code', 'INVALID_CREDENTIAL');
        Carbon::setTestNow();
    }

    public function test_code_confirmation_requires_exactly_six_numeric_digits(): void
    {
        $user = User::factory()->unverified()->create(['displayName' => 'Visitante']);
        $issued = app(AuthenticationChallengeService::class)->issue($user, AuthenticationChallengePurpose::EmailVerification);

        $this->postJson('/api/v1/auth/email-verifications', [
            'challengeId' => $issued->challengeId,
            'code' => '12ab',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }
}
