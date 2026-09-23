<?php

namespace Tests\Feature;

use App\Models\PersistentAuthentication;
use App\Models\User;
use App\Services\Access\PersistentAuthenticationLifecycleService;
use App\Services\Access\ServerSessionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyOtherSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_sessions_and_persistent_authentications_are_revoked(): void
    {
        $user = User::factory()->create();
        $currentPersistent = PersistentAuthentication::query()->create(['idUser' => $user->id, 'secretHash' => 'current']);
        $otherPersistent = PersistentAuthentication::query()->create(['idUser' => $user->id, 'secretHash' => 'other']);
        $this->app['db']->table('session')->insert([
            ['id' => 'current', 'idUser' => $user->id, 'idPersistentAuthentication' => $currentPersistent->id, 'payload' => 'x', 'lastActivityAt' => now()],
            ['id' => 'other', 'idUser' => $user->id, 'idPersistentAuthentication' => $otherPersistent->id, 'payload' => 'x', 'lastActivityAt' => now()],
        ]);

        app(ServerSessionLifecycleService::class)->discardOtherSessions($user->id, 'current');
        app(PersistentAuthenticationLifecycleService::class)->revokeOthers($user->id, $currentPersistent->id);

        $this->assertDatabaseHas('session', ['id' => 'current']);
        $this->assertDatabaseMissing('session', ['id' => 'other']);
        $this->assertNull($currentPersistent->refresh()->revokedAt);
        $this->assertNotNull($otherPersistent->refresh()->revokedAt);
    }
}
