<?php

namespace Tests\Feature;

use App\Models\AuthenticationChallenge;
use App\Models\User;
use App\Services\Access\AuthenticationChallengeLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessPersistenceConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_email_remains_unique_at_database_level(): void
    {
        User::query()->create([
            'email' => 'member@example.test',
            'displayName' => 'Member',
        ]);

        $this->expectException(QueryException::class);

        User::query()->create([
            'email' => 'MEMBER@EXAMPLE.TEST',
            'displayName' => 'Another member',
        ]);
    }

    public function test_authentication_challenge_requires_an_existing_user(): void
    {
        $this->expectException(QueryException::class);

        AuthenticationChallenge::query()->create([
            'idUser' => 999999,
            'purpose' => 'email_verification',
            'secretHash' => hash('sha256', 'unknown-user'),
            'expiresAt' => now()->addMinute(),
        ]);
    }

    public function test_deleting_a_user_cascades_to_access_records(): void
    {
        $user = User::factory()->create();
        $persistentAuthenticationId = 1;
        $challenge = $this->createChallenge($user, 'email_verification');

        $this->app['db']->table('persistentAuthentication')->insert([
            'idUser' => $user->id,
            'secretHash' => hash('sha256', 'persistent-authentication'),
            'lastUsedAt' => now(),
        ]);
        $this->app['db']->table('session')->insert([
            'id' => 'session-to-cascade',
            'idUser' => $user->id,
            'idPersistentAuthentication' => $persistentAuthenticationId,
            'payload' => 'payload',
            'lastActivityAt' => now(),
        ]);

        $user->delete();

        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $challenge->id]);
        $this->assertDatabaseMissing('persistentAuthentication', ['id' => $persistentAuthenticationId]);
        $this->assertDatabaseMissing('session', ['id' => 'session-to-cascade']);
    }

    public function test_discarding_one_users_challenge_does_not_affect_another_user(): void
    {
        $firstChallenge = $this->createChallenge(User::factory()->create(), 'email_verification');
        $secondChallenge = $this->createChallenge(User::factory()->create(), 'email_verification');

        app(AuthenticationChallengeLifecycleService::class)
            ->discardForUserAndPurpose($firstChallenge->idUser, $firstChallenge->purpose);

        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $firstChallenge->id]);
        $this->assertDatabaseHas('authenticationChallenge', ['id' => $secondChallenge->id]);
    }

    private function createChallenge(User $user, string $purpose): AuthenticationChallenge
    {
        return AuthenticationChallenge::query()->create([
            'idUser' => $user->id,
            'purpose' => $purpose,
            'secretHash' => hash('sha256', $purpose.$user->id),
            'expiresAt' => now()->addMinute(),
        ]);
    }
}
