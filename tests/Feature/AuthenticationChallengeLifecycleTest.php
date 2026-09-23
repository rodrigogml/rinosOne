<?php

namespace Tests\Feature;

use App\Models\AuthenticationChallenge;
use App\Models\User;
use App\Services\Access\AuthenticationChallengeLifecycleService;
use App\Services\Access\ServerSessionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationChallengeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_challenges_are_deleted_and_active_challenges_remain(): void
    {
        $user = User::factory()->create();
        $expired = $this->createChallenge($user, 'email_verification', now()->subSecond());
        $active = $this->createChallenge($user, 'passwordless_login', now()->addMinute());

        $removed = app(AuthenticationChallengeLifecycleService::class)->discardExpired();

        $this->assertSame(1, $removed);
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $expired->id]);
        $this->assertDatabaseHas('authenticationChallenge', ['id' => $active->id]);
    }

    public function test_replacing_a_challenge_discards_the_previous_emission_immediately(): void
    {
        $challenge = $this->createChallenge(User::factory()->create(), 'email_verification', now()->addMinute());

        $removed = app(AuthenticationChallengeLifecycleService::class)
            ->discardForUserAndPurpose($challenge->idUser, $challenge->purpose);

        $this->assertSame(1, $removed);
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $challenge->id]);
    }

    public function test_invalidated_server_sessions_are_deleted_immediately(): void
    {
        $user = User::factory()->create();

        $this->app['db']->table('session')->insert([
            'id' => 'session-to-discard',
            'idUser' => $user->id,
            'payload' => 'payload',
            'lastActivityAt' => now(),
        ]);

        $discarded = app(ServerSessionLifecycleService::class)->discard('session-to-discard');

        $this->assertTrue($discarded);
        $this->assertDatabaseMissing('session', ['id' => 'session-to-discard']);
    }

    public function test_cleanup_command_discards_expired_challenges(): void
    {
        $expired = $this->createChallenge(User::factory()->create(), 'email_verification', now()->subSecond());

        $this->artisan('access:purge-expired-challenges')
            ->assertSuccessful();

        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $expired->id]);
    }

    private function createChallenge(User $user, string $purpose, \DateTimeInterface $expiresAt): AuthenticationChallenge
    {
        return AuthenticationChallenge::query()->create([
            'idUser' => $user->id,
            'purpose' => $purpose,
            'secretHash' => hash('sha256', $purpose.$user->id),
            'expiresAt' => $expiresAt,
        ]);
    }
}
