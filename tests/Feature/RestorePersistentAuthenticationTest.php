<?php

namespace Tests\Feature;

use App\Http\Middleware\RestorePersistentAuthentication;
use App\Models\PersistentAuthentication;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RestorePersistentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_persistent_credential_rebuilds_missing_session(): void
    {
        $user = User::factory()->create();
        $secret = bin2hex(random_bytes(32));
        $authentication = PersistentAuthentication::query()->create(['idUser' => $user->id, 'secretHash' => Hash::make($secret), 'lastUsedAt' => now()]);

        Route::get('/api/v1/persistent-authentication-probe', static fn (Request $request) => response()->json(['authenticated' => auth()->check(), 'cookiePresent' => $request->cookie(config('access.authentication.persistentCookieName')) !== null]))
            ->middleware(['web', RestorePersistentAuthentication::class]);
        $cookies = [config('access.authentication.persistentCookieName') => $authentication->id.'.'.$secret];
        $this->withoutMiddleware(EncryptCookies::class)
            ->call('GET', '/api/v1/persistent-authentication-probe', [], $cookies)->assertJsonPath('cookiePresent', true)->assertJsonPath('authenticated', true);
        $this->assertDatabaseHas('persistentAuthentication', ['id' => $authentication->id, 'idUser' => $user->id, 'revokedAt' => null]);
    }

    public function test_revoked_persistent_credential_does_not_restore_session(): void
    {
        $user = User::factory()->create();
        $secret = bin2hex(random_bytes(32));
        $authentication = PersistentAuthentication::query()->create(['idUser' => $user->id, 'secretHash' => Hash::make($secret), 'revokedAt' => now()]);
        Route::get('/api/v1/revoked-persistent-authentication-probe', static fn () => response()->json(['authenticated' => auth()->check()]))
            ->middleware(['web', RestorePersistentAuthentication::class]);

        $this->withoutMiddleware(EncryptCookies::class)
            ->call('GET', '/api/v1/revoked-persistent-authentication-probe', [], [config('access.authentication.persistentCookieName') => $authentication->id.'.'.$secret])
            ->assertJsonPath('authenticated', false);
    }
}
