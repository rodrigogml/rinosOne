<?php

namespace Tests\Feature;

use App\Domain\Person\PersonAddressType;
use App\Domain\Person\PersonAuditAction;
use App\Domain\Person\PersonBankAccountType;
use App\Domain\Person\PersonContactType;
use App\Domain\Person\PersonPixKeyType;
use App\Domain\Person\PersonRelationshipType;
use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonAuditEvent;
use App\Models\PersonBankAccount;
use App\Models\PersonContact;
use App\Models\PersonPixKey;
use App\Models\PersonRelationship;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TenantPersonPersistenceConstraintsTest extends TestCase
{
    public function test_tenant_migration_catalog_contains_the_person_aggregate_migration(): void
    {
        $migrations = app('migrator')->getMigrationFiles([database_path('migrations/tenant')]);

        $this->assertArrayHasKey('2026_09_28_000001_create_person_registration_tables', $migrations);
    }

    public function test_person_migration_declares_the_aggregate_tables_and_cross_schema_references(): void
    {
        Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--pretend' => true,
            '--force' => true,
        ]);

        $output = Artisan::output();

        foreach ([
            'create table "person"',
            'create table "personAddress"',
            'create table "personContact"',
            'create table "personBankAccount"',
            'create table "personPixKey"',
            'create table "personRelationship"',
            'create table "personAuditEvent"',
            'references "rinosone"."country"',
            'references "rinosone"."financialInstitution"',
            'references "rinosone"."user"',
            'on delete cascade on update cascade',
            'on delete set null on update cascade',
        ] as $expectedStatement) {
            $this->assertStringContainsString($expectedStatement, $output);
        }
    }

    public function test_models_bind_to_the_resolved_tenant_connection_and_expose_their_local_relations(): void
    {
        $tenantConnection = config('database.connections.sqlite');
        $tenantConnection['database'] = ':memory:';
        config(['database.connections.tenant-person-test' => $tenantConnection]);
        DB::purge('tenant-person-test');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getName')->once()->andReturn('tenant-person-test');

        $person = (new Person)->forTenantConnection($connection);

        $this->assertSame('tenant-person-test', $person->getConnectionName());
        $this->assertInstanceOf(PersonAddress::class, $person->addresses()->getRelated());
        $this->assertInstanceOf(PersonContact::class, $person->contacts()->getRelated());
        $this->assertInstanceOf(PersonBankAccount::class, $person->bankAccounts()->getRelated());
        $this->assertInstanceOf(PersonPixKey::class, $person->pixKeys()->getRelated());
        $this->assertInstanceOf(PersonRelationship::class, $person->outgoingRelationships()->getRelated());
        $this->assertInstanceOf(PersonRelationship::class, $person->incomingRelationships()->getRelated());
    }

    public function test_models_cast_the_persisted_enums(): void
    {
        $this->assertSame(PersonType::PF, (new Person(['personType' => 'PF']))->personType);
        $this->assertSame(PersonStatus::INACTIVE, (new Person(['status' => 'INACTIVE']))->status);
        $this->assertSame(PersonAddressType::BILLING, (new PersonAddress(['addressType' => 'BILLING']))->addressType);
        $this->assertSame(PersonContactType::WHATSAPP, (new PersonContact(['contactType' => 'WHATSAPP']))->contactType);
        $this->assertSame(PersonBankAccountType::SAVINGS, (new PersonBankAccount(['accountType' => 'SAVINGS']))->accountType);
        $this->assertSame(PersonPixKeyType::RANDOM, (new PersonPixKey(['keyType' => 'RANDOM']))->keyType);
        $this->assertSame(PersonRelationshipType::EMPLOYEE_OF, (new PersonRelationship(['relationshipType' => 'EMPLOYEE_OF']))->relationshipType);
        $this->assertSame(PersonAuditAction::UPDATED, (new PersonAuditEvent(['action' => 'UPDATED']))->action);
    }
}
