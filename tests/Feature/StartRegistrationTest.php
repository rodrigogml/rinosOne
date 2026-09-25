<?php

namespace Tests\Feature;

use App\Mail\Access\EmailVerificationMessage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StartRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_pending_user_and_queues_one_encrypted_verification_message(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/registrations', [
            'email' => 'Visitor@Example.test',
            'displayName' => 'Visitante',
            'rememberMe' => true,
        ])->assertAccepted()->assertJsonPath('message', 'Se possível, enviaremos instruções para o endereço informado.')->assertJsonStructure(['challengeId']);

        $user = User::query()->where('email', 'visitor@example.test')->sole();

        $this->assertNull($user->emailVerifiedAt);
        $this->assertSame('Visitante', $user->displayName);
        $this->assertDatabaseHas('authenticationChallenge', [
            'idUser' => $user->id,
            'purpose' => 'email_verification',
            'rememberMeRequested' => true,
        ]);
        Mail::assertQueued(EmailVerificationMessage::class, function (EmailVerificationMessage $message) use ($user): bool {
            return $message->hasTo($user->email)
                && $message instanceof ShouldBeEncrypted;
        });
    }

    public function test_existing_verified_email_receives_the_same_neutral_response_without_a_new_message(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'member@example.test']);

        $this->postJson('/api/v1/auth/registrations', [
            'email' => 'member@example.test',
            'displayName' => 'Membro',
        ])->assertAccepted()->assertJsonPath('message', 'Se possível, enviaremos instruções para o endereço informado.')->assertJsonStructure(['challengeId']);

        Mail::assertNothingQueued();
    }

    public function test_registration_rejects_invalid_request_data(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/registrations', [
            'email' => 'invalid',
            'displayName' => 'Visitante',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        Mail::assertNothingQueued();
    }

    public function test_pending_registration_can_be_resent_without_creating_another_user(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create(['email' => 'visitor@example.test']);

        $this->postJson('/api/v1/auth/registrations', ['email' => $user->email, 'displayName' => 'Visitante'])->assertAccepted();

        $this->assertSame(1, User::query()->where('email', $user->email)->count());
        $this->assertDatabaseCount('authenticationChallenge', 1);
        Mail::assertQueued(EmailVerificationMessage::class, 1);
    }

    public function test_registration_returns_neutral_rate_limit_response(): void
    {
        config(['access.authentication.emailEmissionLimit' => 1, 'access.authentication.originEmissionLimit' => 10, 'access.authentication.emailResendCooldownSeconds' => 1]);

        $this->postJson('/api/v1/auth/registrations', ['email' => 'visitor@example.test', 'displayName' => 'Visitante'])->assertAccepted();
        $this->travel(2)->seconds();
        $this->postJson('/api/v1/auth/registrations', ['email' => 'visitor@example.test', 'displayName' => 'Visitante'])
            ->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
    }
}
