<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonDocumentConflictException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonIdentityInput;
use App\Domain\Person\PersonType;
use App\Services\Person\PersonIdentityService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonIdentityServiceTest extends TestCase
{
    private ConnectionInterface $firstTenant;

    private ConnectionInterface $secondTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firstTenant = $this->tenantConnection('person_identity_one');
        $this->secondTenant = $this->tenantConnection('person_identity_two');
    }

    public function test_it_preserves_document_uniqueness_within_a_tenant_but_not_between_tenants(): void
    {
        $service = app(PersonIdentityService::class);
        $input = new PersonIdentityInput(PersonType::PF, 'Ada Lovelace', cpf: '529.982.247-25');
        $created = $service->create($this->firstTenant, $input, actorUserId: 7, correlationId: 'people-create-42');

        $this->assertSame(1, $created->version);
        $this->assertSame('CREATED', $this->firstTenant->table('personAuditEvent')->value('action'));
        $this->assertSame(7, $this->firstTenant->table('personAuditEvent')->value('idActorUser'));
        $this->assertSame('people-create-42', $this->firstTenant->table('personAuditEvent')->value('correlationId'));
        $this->expectException(PersonDocumentConflictException::class);
        try {
            $service->create($this->firstTenant, $input);
        } finally {
            $otherTenantPerson = $service->create($this->secondTenant, $input);
            $this->assertSame('52998224725', $otherTenantPerson->cpf);
        }
    }

    public function test_it_updates_only_the_current_version_and_rejects_a_stale_writer(): void
    {
        $service = app(PersonIdentityService::class);
        $created = $service->create($this->firstTenant, new PersonIdentityInput(PersonType::PJ, 'Rinos One', cnpj: '04.252.011/0001-10'));
        $updated = $service->update($this->firstTenant, $created->id, 1, new PersonIdentityInput(PersonType::PJ, 'Rinos One Ltda.', cnpj: '04.252.011/0001-10'), actorUserId: 8, correlationId: 'people-update-42');

        $this->assertSame(2, $updated->version);
        $this->assertSame('UPDATED', $this->firstTenant->table('personAuditEvent')->orderByDesc('id')->value('action'));
        $this->assertSame('people-update-42', $this->firstTenant->table('personAuditEvent')->orderByDesc('id')->value('correlationId'));
        $this->expectException(PersonVersionConflictException::class);
        $service->update($this->firstTenant, $created->id, 1, new PersonIdentityInput(PersonType::PJ, 'Rinos One stale', cnpj: '04.252.011/0001-10'));
    }

    private function tenantConnection(string $name): ConnectionInterface
    {
        config()->set("database.connections.{$name}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
        $connection = DB::connection($name);
        Schema::connection($name)->create('person', function ($table): void {
            $table->id();
            $table->string('personType', 2);
            $table->string('name', 255);
            $table->string('alias', 255)->nullable();
            $table->string('displayName', 511);
            $table->string('cpf', 11)->nullable()->unique();
            $table->string('cnpj', 14)->nullable()->unique();
            $table->string('rg', 40)->nullable();
            $table->string('rgIssuer', 60)->nullable();
            $table->string('pisNis', 20)->nullable();
            $table->string('passportNumber', 40)->nullable();
            $table->string('foreignDocumentNumber', 60)->nullable();
            $table->date('birthDate')->nullable();
            $table->date('foundationDate')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });
        Schema::connection($name)->create('personAuditEvent', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->string('action', 16);
            $table->timestamp('occurredAt');
            $table->string('correlationId', 128)->nullable();
        });

        return $connection;
    }
}
