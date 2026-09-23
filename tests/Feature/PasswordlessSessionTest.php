<?php

namespace Tests\Feature;

use App\Mail\Access\PasswordlessLoginMessage;
use App\Models\AuthenticationChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordlessSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_confirm_the_code_from_the_message_that_was_requested(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => $user->email, 'rememberMe' => true])
            ->assertAccepted()
            ->assertJsonPath('message', 'Se possível, enviaremos instruções para o endereço informado.')
            ->assertJsonStructure(['challengeId']);

        $challenge = AuthenticationChallenge::query()->where('idUser', $user->id)->sole();
        $this->assertSame($challenge->id, $response->json('challengeId'));
        Mail::assertQueued(PasswordlessLoginMessage::class, function (PasswordlessLoginMessage $message) use ($challenge, $user): bool {
            return $message->hasTo($user->email) && $message->challengeId === $challenge->id;
        });

        $message = Mail::queued(PasswordlessLoginMessage::class)->first();
        $this->postJson('/api/v1/auth/passwordless-sessions/confirmations', ['challengeId' => $message->challengeId, 'code' => $message->code])
            ->assertCreated()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissing(['email'])
            ->assertCookie(config('access.authentication.persistentCookieName'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('authenticationChallenge', ['id' => $challenge->id]);
    }

    public function test_code_consumption_invalidates_the_link_from_the_same_emission(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => $user->email])->assertAccepted();
        $message = Mail::queued(PasswordlessLoginMessage::class)->first();

        $this->postJson('/api/v1/auth/passwordless-sessions/confirmations', ['challengeId' => $message->challengeId, 'code' => $message->code])->assertCreated();
        $this->postJson('/api/v1/auth/passwordless-sessions/link-confirmations', ['challengeId' => $message->challengeId, 'token' => $message->token])
            ->assertBadRequest()
            ->assertJsonPath('code', 'INVALID_CREDENTIAL');
    }

    public function test_unknown_or_unverified_email_receives_the_same_neutral_response_without_a_message(): void
    {
        Mail::fake();
        $unverified = User::factory()->unverified()->create();

        $unknown = $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => 'unknown@example.test']);
        $ineligible = $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => $unverified->email]);

        $unknown->assertAccepted()->assertJsonPath('message', 'Se possível, enviaremos instruções para o endereço informado.');
        $ineligible->assertAccepted()->assertJsonPath('message', 'Se possível, enviaremos instruções para o endereço informado.');
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $unknown->json('challengeId'));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ineligible->json('challengeId'));
        Mail::assertNothingQueued();
    }

    public function test_new_request_replaces_the_previous_passwordless_emission(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => $user->email])->assertAccepted();
        $first = Mail::queued(PasswordlessLoginMessage::class)->first();
        $this->postJson('/api/v1/auth/passwordless-sessions', ['email' => $user->email])->assertAccepted();
        $second = Mail::queued(PasswordlessLoginMessage::class)->last();

        $this->postJson('/api/v1/auth/passwordless-sessions/confirmations', ['challengeId' => $first->challengeId, 'code' => $first->code])
            ->assertBadRequest()
            ->assertJsonPath('code', 'INVALID_CREDENTIAL');
        $this->postJson('/api/v1/auth/passwordless-sessions/confirmations', ['challengeId' => $second->challengeId, 'code' => $second->code])
            ->assertCreated();
    }
}
