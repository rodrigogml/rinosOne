<?php

namespace Tests\Unit;

use App\Infrastructure\Tenant\TenantSchemaName;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class TenantSchemaNameTest extends TestCase
{
    private const TENANT_ID = '42';

    public function test_it_derives_a_schema_name_from_a_valid_unsigned_integer(): void
    {
        $schemaName = app(TenantSchemaName::class);

        $this->assertSame('rinosone_42', $schemaName->fromTenantId(self::TENANT_ID));
    }

    public function test_it_rejects_an_identifier_that_is_not_an_unsigned_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(TenantSchemaName::class)->fromTenantId('rinosone; DROP DATABASE rinosone');
    }

    public function test_it_rejects_an_invalid_schema_prefix_configuration(): void
    {
        config(['access.schemas.tenantPrefix' => 'rinosone-']);

        $this->expectException(LogicException::class);

        app(TenantSchemaName::class)->fromTenantId(self::TENANT_ID);
    }
}
