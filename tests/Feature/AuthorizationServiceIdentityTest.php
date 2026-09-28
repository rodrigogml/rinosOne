<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationServiceIdentityService;
use App\Services\Authorization\Advanced\ServiceIdentityAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationServiceIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_credential_is_shown_once_validated_by_hash_and_limited_to_its_own_permission_cap(): void
    {
        $owner = User::factory()->create();
        $read = AuthorizationPermission::query()->create(['key' => 'personal.integration.read', 'displayName' => 'Read', 'description' => 'Read.', 'scope' => 'PERSONAL', 'systemManaged' => false, 'active' => true]);
        $write = AuthorizationPermission::query()->create(['key' => 'personal.integration.write', 'displayName' => 'Write', 'description' => 'Write.', 'scope' => 'PERSONAL', 'systemManaged' => false, 'active' => true]);
        $identities = app(AuthorizationServiceIdentityService::class);
        $identity = $identities->create($owner, 'Export service', 'Exports data', AuthorizationScope::Personal);
        $authorization = app(ServiceIdentityAuthorizationService::class);
        $authorization->grant($identity, $read);
        $authorization->grant($identity, $write);
        $issued = $identities->issueCredential($identity, 'Read-only export', [$read->key]);
        $credential = $identities->validateApiKey($issued->apiKey);

        $this->assertNotNull($credential);
        $this->assertTrue($authorization->allows($credential, $read->key));
        $this->assertFalse($authorization->allows($credential, $write->key));
        $this->assertNull($identities->validateApiKey($issued->apiKey.'invalid'));
        $this->assertDatabaseMissing('auth_audit_event', ['after' => json_encode(['apiKey' => $issued->apiKey])]);

        $identities->revokeCredential($credential);
        $this->assertNull($identities->validateApiKey($issued->apiKey));
    }
}
