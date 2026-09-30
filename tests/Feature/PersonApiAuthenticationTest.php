<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Domain\Tenant\TenantState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    public function test_people_routes_require_an_authenticated_session_without_revealing_tenant_or_person_data(): void
    {
        $this->getJson('/api/v1/tenants/999/people/888')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'PERSON_AUTHENTICATION_REQUIRED')
            ->assertJsonPath('error.message', 'Autenticação necessária para acessar Pessoas.')
            ->assertJsonMissingPath('person')
            ->assertJsonMissingPath('tenant')
            ->assertJsonMissingPath('connection')
            ->assertJsonMissingPath('error.tenantId')
            ->assertJsonMissingPath('error.personId');
    }

    public function test_authenticated_user_without_tenant_context_receives_safe_forbidden_response(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Restricted', 'state' => TenantState::Active]);

        $this->actingAs($user)->getJson("/api/v1/tenants/{$tenant->id}/people/99")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERSON_ACCESS_DENIED')
            ->assertJsonMissingPath('person')
            ->assertJsonMissingPath('connection')
            ->assertJsonMissingPath('error.tenantId')
            ->assertJsonMissingPath('error.personId');
    }

    public function test_denied_response_does_not_distinguish_an_unknown_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/tenants/999999/people/99')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERSON_ACCESS_DENIED')
            ->assertJsonMissingPath('person')
            ->assertJsonMissingPath('tenant');
    }

    public function test_people_route_miss_uses_the_same_safe_not_found_envelope(): void
    {
        $this->getJson('/api/v1/tenants/999/people/not-a-number')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PERSON_NOT_FOUND')
            ->assertJsonPath('error.message', 'Pessoa não encontrada nesta organização.')
            ->assertJsonMissingPath('person')
            ->assertJsonMissingPath('tenant')
            ->assertJsonMissingPath('connection');
    }
}
