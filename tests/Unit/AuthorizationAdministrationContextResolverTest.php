<?php

namespace Tests\Unit;

use App\Domain\Authorization\AuthorizationDecision;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationCapabilities;
use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use App\Services\Authorization\Administration\AuthorizationAdministrationContextResolver;
use App\Services\Authorization\AuthorizationService;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class AuthorizationAdministrationContextResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_personal_context_is_bound_to_the_authenticated_users_workspace(): void
    {
        $resolver = new AuthorizationAdministrationContextResolver(Mockery::mock(AuthorizationService::class));

        $context = $resolver->forPersonal($this->actor());

        $this->assertSame(AuthorizationScope::Personal, $context->scope);
        $this->assertNull($context->tenantId);
        $this->assertTrue($context->capabilities->canReadAccess);
        $this->assertFalse($context->capabilities->canManageRoles);
        $this->assertTrue($context->capabilities->canManageSharing);
        $this->assertFalse($context->capabilities->canUseAdvancedControls);
    }

    public function test_tenant_context_uses_membership_bound_permission_before_platform_supervision(): void
    {
        $authorization = Mockery::mock(AuthorizationService::class);
        $actor = $this->actor();
        $authorization->shouldReceive('check')->with($actor, 'tenant.authorization.read', AuthorizationScope::Tenant, 12)->once()->andReturn(new AuthorizationDecision(true, 'GRANT_APPLIES'));
        $authorization->shouldReceive('check')->with($actor, 'tenant.authorization.manage', AuthorizationScope::Tenant, 12)->once()->andReturn(new AuthorizationDecision(true, 'GRANT_APPLIES'));
        $resolver = new AuthorizationAdministrationContextResolver($authorization);

        $context = $resolver->forTenant($actor, 12);

        $this->assertSame(AuthorizationScope::Tenant, $context->scope);
        $this->assertSame(12, $context->tenantId);
        $this->assertTrue($context->capabilities->canReadAccess);
        $this->assertTrue($context->capabilities->canManageRoles);
        $this->assertTrue($context->capabilities->canManageSharing);
        $this->assertTrue($context->capabilities->canUseAdvancedControls);
    }

    public function test_platform_supervision_does_not_create_a_tenant_context(): void
    {
        $authorization = Mockery::mock(AuthorizationService::class);
        $actor = $this->actor();
        $authorization->shouldReceive('check')->with($actor, 'platform.authorization.tenant.read', AuthorizationScope::Platform)->once()->andReturn(new AuthorizationDecision(true, 'GRANT_APPLIES'));
        $authorization->shouldReceive('check')->with($actor, 'platform.authorization.tenant.manage', AuthorizationScope::Platform)->once()->andReturn(new AuthorizationDecision(false, 'NO_APPLICABLE_GRANT'));
        $resolver = new AuthorizationAdministrationContextResolver($authorization);

        $context = $resolver->forPlatform($actor);

        $this->assertSame(AuthorizationScope::Platform, $context->scope);
        $this->assertNull($context->tenantId);
        $this->assertTrue($context->capabilities->canReadAccess);
        $this->assertFalse($context->capabilities->canManageRoles);
        $this->assertFalse($context->capabilities->canManageSharing);
    }

    public function test_invalid_or_cross_scope_context_is_rejected(): void
    {
        $resolver = new AuthorizationAdministrationContextResolver(Mockery::mock(AuthorizationService::class));

        try {
            $resolver->forTenant($this->actor(), 0);
            $this->fail('An invalid tenant context must be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        new AuthorizationAdministrationContext(
            AuthorizationScope::Personal,
            12,
            new AuthorizationAdministrationCapabilities(true, false, true, false),
        );
    }

    private function actor(): User
    {
        $actor = new User;
        $actor->setAttribute('id', 7);

        return $actor;
    }
}
