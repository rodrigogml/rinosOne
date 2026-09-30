<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_receives_only_the_safe_empty_catalog_and_virtual_shared_root(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/drive/catalog')
            ->assertOk()
            ->assertJsonPath('drives.0.target.kind', 'personal')
            ->assertJsonPath('drives.0.target.tenantId', null)
            ->assertJsonPath('drives.0.displayName', 'Meu Drive')
            ->assertJsonPath('sharedWithMe.kind', 'shared-with-me');

        $this->actingAs($user)
            ->getJson('/api/v1/drive/shared-with-me')
            ->assertOk()
            ->assertExactJson(['folders' => [], 'files' => []]);
    }
}
