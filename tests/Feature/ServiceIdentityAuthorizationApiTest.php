<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationServiceIdentityService;
use App\Services\Authorization\Advanced\ServiceIdentityAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceIdentityAuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_service_api_key_can_check_only_its_explicit_capability(): void
    {
        $owner = User::factory()->create();
        $allowed = AuthorizationPermission::query()->create(['key' => 'personal.api.allowed', 'displayName' => 'Allowed', 'description' => 'Allowed.', 'scope' => 'PERSONAL', 'systemManaged' => false, 'active' => true]);
        $denied = AuthorizationPermission::query()->create(['key' => 'personal.api.denied', 'displayName' => 'Denied', 'description' => 'Denied.', 'scope' => 'PERSONAL', 'systemManaged' => false, 'active' => true]);
        $identities = app(AuthorizationServiceIdentityService::class);
        $identity = $identities->create($owner, 'API integration', 'Tests API access', AuthorizationScope::Personal);
        app(ServiceIdentityAuthorizationService::class)->grant($identity, $allowed);
        $issued = $identities->issueCredential($identity, 'API key');

        $this->getJson('/api/v1/service/authorization/check?permissionKey='.$allowed->key, ['X-Rinos-Api-Key' => $issued->apiKey])
            ->assertOk()->assertExactJson(['allowed' => true]);
        $this->getJson('/api/v1/service/authorization/check?permissionKey='.$denied->key, ['X-Rinos-Api-Key' => $issued->apiKey])
            ->assertOk()->assertExactJson(['allowed' => false]);
        $this->getJson('/api/v1/service/authorization/check?permissionKey='.$allowed->key)
            ->assertUnauthorized()->assertJsonPath('code', 'SERVICE_API_KEY_INVALID');
    }
}
