<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_set_a_password_that_meets_policy(): void
    {
        $user = User::factory()->create(['passwordHash' => null]);
        $this->actingAs($user)->putJson('/api/v1/auth/password', ['password' => 'Abcde1'])
            ->assertNoContent();
        $this->assertTrue(Hash::check('Abcde1', $user->refresh()->passwordHash));
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/api/v1/auth/password', ['password' => 'abcdef'])
            ->assertUnprocessable();
    }
}
