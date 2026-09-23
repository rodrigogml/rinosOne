<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_minimum_current_session_state(): void
    {
        $user = User::factory()->create(['passwordHash' => null]);
        $this->actingAs($user)->getJson('/api/v1/auth/session')
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.passwordDefined', false)->assertJsonMissing(['sessionId', 'device', 'email']);
    }
}
