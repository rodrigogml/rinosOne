<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreatePasswordSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_with_correct_password_receives_session(): void
    {
        $user = User::factory()->create(['passwordHash' => 'Abcde1']);
        $this->postJson('/api/v1/auth/password-sessions', ['email' => $user->email, 'password' => 'Abcde1', 'rememberMe' => true])
            ->assertCreated()->assertJsonPath('user.id', $user->id)->assertJsonMissing(['email'])->assertCookie(config('access.authentication.persistentCookieName'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_response_does_not_disclose_account_state(): void
    {
        $user = User::factory()->create(['passwordHash' => 'Abcde1']);
        $known = $this->postJson('/api/v1/auth/password-sessions', ['email' => $user->email, 'password' => 'wrong']);
        $unknown = $this->postJson('/api/v1/auth/password-sessions', ['email' => 'unknown@example.test', 'password' => 'wrong']);
        $known->assertBadRequest()->assertExactJson($unknown->json());
    }

    public function test_password_login_without_remember_me_does_not_create_persistent_authentication(): void
    {
        $user = User::factory()->create(['passwordHash' => 'Abcde1']);

        $this->postJson('/api/v1/auth/password-sessions', ['email' => $user->email, 'password' => 'Abcde1'])
            ->assertCreated()->assertCookieMissing(config('access.authentication.persistentCookieName'));

        $this->assertDatabaseCount('persistentAuthentication', 0);
    }

    public function test_remembered_password_login_issues_a_cookie_backed_by_a_hashed_persistent_credential(): void
    {
        $user = User::factory()->create(['passwordHash' => 'Abcde1']);
        $response = $this->postJson('/api/v1/auth/password-sessions', ['email' => $user->email, 'password' => 'Abcde1', 'rememberMe' => true])
            ->assertCreated()
            ->assertCookie(config('access.authentication.persistentCookieName'));

        $cookie = $response->getCookie(config('access.authentication.persistentCookieName'));
        $this->assertNotNull($cookie);
        [$authenticationId, $secret] = explode('.', $cookie->getValue(), 2);
        $authentication = $this->app['db']->table('persistentAuthentication')->where('id', $authenticationId)->sole();

        $this->assertSame($user->id, $authentication->idUser);
        $this->assertTrue(Hash::check($secret, $authentication->secretHash));
    }

    public function test_unverified_or_passwordless_account_cannot_start_password_session(): void
    {
        $unverified = User::factory()->unverified()->create(['passwordHash' => 'Abcde1']);
        $withoutPassword = User::factory()->create(['passwordHash' => null]);

        $unverifiedResponse = $this->postJson('/api/v1/auth/password-sessions', ['email' => $unverified->email, 'password' => 'Abcde1']);
        $withoutPasswordResponse = $this->postJson('/api/v1/auth/password-sessions', ['email' => $withoutPassword->email, 'password' => 'Abcde1']);

        $unverifiedResponse->assertBadRequest()->assertExactJson($withoutPasswordResponse->json());
        $this->assertGuest();
    }
}
