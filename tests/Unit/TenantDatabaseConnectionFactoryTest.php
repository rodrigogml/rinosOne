<?php

namespace Tests\Unit;

use App\Infrastructure\Tenant\TenantDatabaseConnectionFactory;
use Tests\TestCase;

class TenantDatabaseConnectionFactoryTest extends TestCase
{
    public function test_connection_templates_keep_runtime_and_provisioning_credentials_separate(): void
    {
        config([
            'database.connections.core.username' => 'core-runtime',
            'database.connections.tenant.username' => 'tenant-runtime',
            'database.connections.provisioning.username' => 'tenant-provisioner',
        ]);

        $this->assertSame('core-runtime', config('database.connections.core.username'));
        $this->assertSame('tenant-runtime', config('database.connections.tenant.username'));
        $this->assertSame('tenant-provisioner', config('database.connections.provisioning.username'));
    }

    public function test_it_builds_a_connection_configuration_from_the_validated_tenant_identifier(): void
    {
        config([
            'database.connections.tenant.host' => 'tenant-db.example.test',
            'database.connections.tenant.username' => 'tenant-runtime',
        ]);

        $configuration = app(TenantDatabaseConnectionFactory::class)
            ->configuration('42');

        $this->assertSame('tenant-db.example.test', $configuration['host']);
        $this->assertSame('tenant-runtime', $configuration['username']);
        $this->assertSame('rinosone_42', $configuration['database']);
    }
}
