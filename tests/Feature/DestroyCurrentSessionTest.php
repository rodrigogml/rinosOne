<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyCurrentSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_end_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->deleteJson('/api/v1/auth/session')
            ->assertNoContent()->assertCookieExpired(config('access.authentication.persistentCookieName'));
        $this->assertGuest();
    }
}
