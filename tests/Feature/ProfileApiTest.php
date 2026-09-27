<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_read_only_their_own_profile(): void
    {
        $currentUser = User::factory()->create(['displayName' => 'Ana Souza']);
        User::factory()->create(['displayName' => 'Other user']);

        $this->actingAs($currentUser)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertExactJson([
                'user' => ['id' => $currentUser->id, 'displayName' => 'Ana Souza'],
                'avatar' => ['available' => false, 'url' => null, 'updatedAt' => null],
            ]);
    }

    public function test_an_authenticated_user_can_update_only_their_own_display_name(): void
    {
        $currentUser = User::factory()->create(['displayName' => 'Ana Souza']);
        $otherUser = User::factory()->create(['displayName' => 'Other user']);

        $this->actingAs($currentUser)->patchJson('/api/v1/profile', ['displayName' => 'Ana Sousa'])
            ->assertOk()
            ->assertJsonPath('user.id', $currentUser->id)
            ->assertJsonPath('user.displayName', 'Ana Sousa');

        $this->assertSame('Ana Sousa', $currentUser->refresh()->displayName);
        $this->assertSame('Other user', $otherUser->refresh()->displayName);
    }

    public function test_an_invalid_display_name_is_rejected_without_changing_the_persisted_value(): void
    {
        $user = User::factory()->create(['displayName' => 'Ana Souza']);

        foreach (['', '   ', str_repeat('A', 256)] as $invalidDisplayName) {
            $this->actingAs($user)->patchJson('/api/v1/profile', ['displayName' => $invalidDisplayName])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['errors' => ['displayName']]);

            $this->assertSame('Ana Souza', $user->refresh()->displayName);
        }
    }

    public function test_the_current_session_projects_the_new_display_name_without_reauthentication(): void
    {
        $user = User::factory()->create(['displayName' => 'Ana Souza']);

        $this->actingAs($user)->patchJson('/api/v1/profile', ['displayName' => 'Ana Sousa'])
            ->assertOk();

        $this->getJson('/api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.displayName', 'Ana Sousa');
    }

    public function test_profile_routes_require_an_authenticated_session(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/profile', ['displayName' => 'Ana Sousa'])->assertUnauthorized();
    }
}
