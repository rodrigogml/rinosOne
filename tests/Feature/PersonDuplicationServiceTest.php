<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonDuplicationOptions;
use App\Services\Person\PersonDuplicationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonDuplicationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function (Blueprint $table): void {
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
        Schema::create('personAddress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('label');
            $table->string('addressType');
            $table->unsignedBigInteger('idCountry')->nullable();
            $table->unsignedBigInteger('idBrazilState')->nullable();
            $table->unsignedBigInteger('idBrazilMunicipality')->nullable();
            $table->unsignedBigInteger('idLocalityReference')->nullable();
            $table->string('stateText')->nullable();
            $table->string('cityText')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('reference')->nullable();
            $table->string('postalCode')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personContact', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('contactType');
            $table->string('value');
            $table->string('normalizedValue');
            $table->string('description')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personBankAccount', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('label');
            $table->unsignedBigInteger('idFinancialInstitution')->nullable();
            $table->string('accountType');
            $table->string('agency')->nullable();
            $table->string('agencyDigit')->nullable();
            $table->string('accountNumber');
            $table->string('accountDigit')->nullable();
            $table->string('status');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personPixKey', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('keyType');
            $table->string('keyValue');
            $table->string('normalizedKeyValue');
            $table->string('status');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personAuditEvent', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->string('action');
            $table->timestamp('occurredAt');
            $table->string('correlationId')->nullable();
        });

        DB::table('person')->insert([
            'id' => 1,
            'personType' => 'PF',
            'name' => 'Ada Lovelace',
            'alias' => 'Ada',
            'displayName' => 'Ada Lovelace',
            'cpf' => '52998224725',
            'rg' => 'MG-12.345.678',
            'status' => 'ACTIVE',
            'version' => 3,
        ]);
        DB::table('personAddress')->insert(['id' => 10, 'idPerson' => 1, 'label' => 'Casa', 'addressType' => 'RESIDENTIAL', 'idCountry' => 1, 'street' => 'Rua Ada']);
        DB::table('personContact')->insert(['id' => 20, 'idPerson' => 1, 'contactType' => 'EMAIL', 'value' => 'ADA@EXAMPLE.TEST', 'normalizedValue' => 'ada@example.test']);
        DB::table('personBankAccount')->insert(['id' => 30, 'idPerson' => 1, 'label' => 'Conta', 'accountType' => 'CHECKING', 'accountNumber' => '123', 'status' => 'ACTIVE']);
        DB::table('personPixKey')->insert(['id' => 40, 'idPerson' => 1, 'keyType' => 'EMAIL', 'keyValue' => 'ada@example.test', 'normalizedKeyValue' => 'ada@example.test', 'status' => 'ACTIVE']);
    }

    public function test_it_copies_identity_without_unique_documents(): void
    {
        $copy = app(PersonDuplicationService::class)->duplicate(DB::connection(), 1, new PersonDuplicationOptions);

        $this->assertSame('Ada Lovelace', $copy->name);
        $this->assertSame('Ada', $copy->alias);
        $this->assertNull($copy->cpf);
        $this->assertNull($copy->cnpj);
        $this->assertSame(1, $copy->version);
        $this->assertNotSame(1, $copy->id);
        $this->assertSame(1, DB::table('personAddress')->count());
        $this->assertDatabaseHas('personAuditEvent', ['personId' => $copy->id, 'action' => 'CREATED']);
    }

    public function test_it_copies_only_the_explicitly_selected_collections_into_independent_rows(): void
    {
        $copy = app(PersonDuplicationService::class)->duplicate(DB::connection(), 1, new PersonDuplicationOptions(
            copyAddresses: true,
            copyContacts: true,
            copyBankAccounts: true,
            copyPixKeys: true,
        ));

        $this->assertSame('Rua Ada', DB::table('personAddress')->where('idPerson', $copy->id)->value('street'));
        $this->assertSame('ada@example.test', DB::table('personContact')->where('idPerson', $copy->id)->value('normalizedValue'));
        $this->assertSame('123', DB::table('personBankAccount')->where('idPerson', $copy->id)->value('accountNumber'));
        $this->assertSame('ada@example.test', DB::table('personPixKey')->where('idPerson', $copy->id)->value('normalizedKeyValue'));
        $this->assertSame(1, DB::table('personAddress')->where('idPerson', $copy->id)->count());
        $this->assertSame(1, DB::table('personContact')->where('idPerson', $copy->id)->count());
        $this->assertSame(1, DB::table('personBankAccount')->where('idPerson', $copy->id)->count());
        $this->assertSame(1, DB::table('personPixKey')->where('idPerson', $copy->id)->count());
    }

    public function test_it_rejects_a_stale_source_version_before_creating_a_copy(): void
    {
        $this->expectException(PersonVersionConflictException::class);
        app(PersonDuplicationService::class)->duplicate(DB::connection(), 1, new PersonDuplicationOptions, expectedVersion: 2);
    }
}
