<?php

namespace Tests\Unit;

use App\Infrastructure\Tenant\TenantSchemaName;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class TenantSchemaNameTest extends TestCase
{
    private const TENANT_ID = '01J7M2X4P9K6V3Q8R5S0T1V2W3';

    public function test_it_derives_a_lowercase_schema_name_from_a_valid_ulid(): void
    {
        $schemaName = app(TenantSchemaName::class);

        $this->assertSame('rinosone_01j7m2x4p9k6v3q8r5s0t1v2w3', $schemaName->fromTenantId(self::TENANT_ID));
    }

    public function test_it_rejects_an_identifier_that_is_not_a_ulid(): void
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
