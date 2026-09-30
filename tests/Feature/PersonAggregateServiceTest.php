<?php

namespace Tests\Feature;

use App\Domain\Person\PersonIdentityInput;
use App\Domain\Person\PersonType;
use App\Domain\Person\Exception\PersonValidationException;
use App\Http\Dto\Person\PersonAggregateData;
use App\Services\Person\PersonAggregateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonAggregateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->string('personType');
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('displayName');
            $table->string('cpf')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('rg')->nullable();
            $table->string('rgIssuer')->nullable();
            $table->string('pisNis')->nullable();
            $table->string('passportNumber')->nullable();
            $table->string('foreignDocumentNumber')->nullable();
            $table->date('birthDate')->nullable();
            $table->date('foundationDate')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personAuditEvent', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->string('action');
            $table->timestamp('occurredAt');
            $table->string('correlationId')->nullable();
        });
        Schema::create('personContact', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('contactType');
            $table->string('value');
            $table->string('normalizedValue');
            $table->string('description')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function test_it_creates_an_identity_and_audit_event_inside_the_tenant_transaction(): void
    {
        $person = app(PersonAggregateService::class)->create(DB::connection(), new PersonAggregateData(
            new PersonIdentityInput(PersonType::PF, 'Ada Lovelace'),
            null,
            null,
            null,
            null,
            null,
        ), actorUserId: 9, correlationId: 'idempotency-key');

        $this->assertSame('Ada Lovelace', $person->name);
        $this->assertDatabaseHas('personAuditEvent', ['personId' => $person->id, 'action' => 'CREATED', 'idActorUser' => 9, 'correlationId' => 'idempotency-key']);
    }

    public function test_it_updates_the_identity_with_the_expected_version(): void
    {
        $service = app(PersonAggregateService::class);
        $person = $service->create(DB::connection(), new PersonAggregateData(new PersonIdentityInput(PersonType::PF, 'Ada Lovelace'), null, null, null, null, null));
        $updated = $service->update(DB::connection(), $person->id, 1, new PersonAggregateData(new PersonIdentityInput(PersonType::PF, 'Ada King'), null, null, null, null, null));

        $this->assertSame('Ada King', $updated->name);
        $this->assertSame(2, $updated->version);
        $this->assertDatabaseHas('personAuditEvent', ['personId' => $person->id, 'action' => 'UPDATED']);
    }

    public function test_it_rolls_back_identity_and_audit_when_a_related_item_is_invalid(): void
    {
        $this->expectException(PersonValidationException::class);

        try {
            app(PersonAggregateService::class)->create(DB::connection(), new PersonAggregateData(
                new PersonIdentityInput(PersonType::PF, 'Ada Lovelace'),
                null,
                [['contactType' => 'EMAIL', 'value' => 'email inválido']],
                null,
                null,
                null,
            ), actorUserId: 9, correlationId: 'rollback-test');
        } finally {
            $this->assertDatabaseCount('person', 0);
            $this->assertDatabaseCount('personContact', 0);
            $this->assertDatabaseCount('personAuditEvent', 0);
        }
    }
}
