<?php

namespace Tests\Feature;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Services\Person\PersonQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonQueryServiceTest extends TestCase
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
            $table->string('status');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personContact', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('contactType');
            $table->string('value');
            $table->string('normalizedValue');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        foreach (['personAddress', 'personBankAccount', 'personPixKey'] as $tableName) {
            Schema::create($tableName, function ($table): void {
                $table->id();
                $table->unsignedBigInteger('idPerson');
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
            });
        }
        DB::table('person')->insert([
            ['id' => 1, 'personType' => 'PF', 'name' => 'Ada Lovelace', 'alias' => 'Ada', 'displayName' => 'Ada Lovelace', 'cpf' => '52998224725', 'cnpj' => null, 'status' => 'ACTIVE'],
            ['id' => 2, 'personType' => 'PJ', 'name' => 'Beta Ltda.', 'alias' => null, 'displayName' => 'Beta Ltda.', 'cpf' => null, 'cnpj' => '12345678000195', 'status' => 'INACTIVE'],
            ['id' => 3, 'personType' => 'PF', 'name' => 'Carol Silva', 'alias' => null, 'displayName' => 'Carol Silva', 'cpf' => null, 'cnpj' => null, 'status' => 'ACTIVE'],
        ]);
        DB::table('personContact')->insert(['idPerson' => 1, 'contactType' => 'EMAIL', 'value' => 'Ada@Example.test', 'normalizedValue' => 'ada@example.test']);
    }

    public function test_it_searches_only_the_current_tenant_connection_and_defaults_to_active_people(): void
    {
        $page = app(PersonQueryService::class)->paginate(DB::connection(), 'ada@example.test', null, PersonStatus::ACTIVE, 1, 50);

        $this->assertSame([1], $page->pluck('id')->all());
        $this->assertSame(1, $page->total());
        $this->assertSame(1, $page->first()->contacts_count);
    }

    public function test_it_filters_orders_and_paginates_by_the_stable_contract(): void
    {
        $page = app(PersonQueryService::class)->paginate(DB::connection(), null, PersonType::PF, PersonStatus::ACTIVE, 2, 1);

        $this->assertSame([3], $page->pluck('id')->all());
        $this->assertSame(2, $page->lastPage());
        $this->assertSame(0, $page->first()->contacts_count);
        $this->assertTrue(app(PersonQueryService::class)->detail(DB::connection(), 1)->relationLoaded('contacts'));
        $this->assertNull(app(PersonQueryService::class)->detail(DB::connection(), 99));
    }

    public function test_it_never_uses_rows_from_another_tenant_connection(): void
    {
        config()->set('database.connections.people_other', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('people_other');
        Schema::connection('people_other')->create('person', function ($table): void {
            $table->id();
            $table->string('personType');
            $table->string('name');
            $table->string('displayName');
            $table->string('status');
        });
        Schema::connection('people_other')->create('personContact', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('normalizedValue');
        });
        DB::connection('people_other')->table('person')->insert(['id' => 99, 'personType' => 'PF', 'name' => 'Other tenant', 'displayName' => 'Other tenant', 'status' => 'ACTIVE']);

        $page = app(PersonQueryService::class)->paginate(DB::connection('people_other'), null, null, PersonStatus::ACTIVE, 1, 50);

        $this->assertSame([99], $page->pluck('id')->all());
        $this->assertSame([1, 3], app(PersonQueryService::class)->paginate(DB::connection(), null, null, PersonStatus::ACTIVE, 1, 50)->pluck('id')->all());
    }
}
