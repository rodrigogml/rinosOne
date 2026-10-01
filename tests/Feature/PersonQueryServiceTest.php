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
            Schema::create($tableName, function ($table) use ($tableName): void {
                $table->id();
                $table->unsignedBigInteger('idPerson');
                if ($tableName === 'personBankAccount') {
                    $table->string('label')->nullable();
                    $table->string('accountType')->nullable();
                    $table->string('status')->nullable();
                    $table->string('agency')->nullable();
                    $table->string('accountNumber')->nullable();
                    $table->unsignedBigInteger('idFinancialInstitution')->nullable();
                }
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
        $page = app(PersonQueryService::class)->paginate(DB::connection(), 'Ada', null, PersonStatus::ACTIVE, 1, 50);

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

    public function test_it_returns_lazy_ranges_and_marks_only_selected_rows_outside_the_active_search(): void
    {
        $result = app(PersonQueryService::class)->lazy(
            DB::connection(),
            'Ada',
            null,
            null,
            PersonStatus::ACTIVE,
            [['column' => 'displayName', 'direction' => 'asc']],
            0,
            100,
            [1, 3],
            false,
            [1, 3],
        );

        $this->assertSame([1, 3], $result['people']->pluck('id')->all());
        $this->assertSame([1], $result['matchingIds']);
        $this->assertSame(1, $result['matchedTotal']);
        $this->assertSame(1, $result['hiddenSelectedTotal']);
    }

    public function test_it_applies_the_requested_sorting_priority_to_lazy_ranges(): void
    {
        $result = app(PersonQueryService::class)->lazy(
            DB::connection(),
            null,
            null,
            null,
            PersonStatus::ACTIVE,
            [
                ['column' => 'personType', 'direction' => 'asc'],
                ['column' => 'displayName', 'direction' => 'desc'],
            ],
            0,
            100,
        );

        $this->assertSame([3, 1], $result['people']->pluck('id')->all());
    }

    public function test_it_limits_select_all_to_an_explicit_safe_id_set(): void
    {
        $service = app(PersonQueryService::class);

        $allowed = $service->selectionIds(DB::connection(), null, null, null, PersonStatus::ACTIVE, 2);
        $refused = $service->selectionIds(DB::connection(), null, null, null, PersonStatus::ACTIVE, 1);

        $this->assertSame([1, 3], $allowed['ids']);
        $this->assertFalse($allowed['exceedsLimit']);
        $this->assertSame([], $refused['ids']);
        $this->assertSame(2, $refused['total']);
        $this->assertTrue($refused['exceedsLimit']);
    }

    public function test_it_distinguishes_any_related_record_from_the_same_bank_account(): void
    {
        DB::table('personBankAccount')->insert([
            ['idPerson' => 1, 'label' => 'BTG', 'accountType' => 'CHECKING', 'status' => 'ACTIVE'],
            ['idPerson' => 1, 'label' => 'Broker', 'accountType' => 'INVESTMENT', 'status' => 'ACTIVE'],
            ['idPerson' => 3, 'label' => 'BTG', 'accountType' => 'INVESTMENT', 'status' => 'ACTIVE'],
        ]);
        $any = ['kind' => 'group', 'combinator' => 'AND', 'negated' => false, 'matchMode' => 'ANY_RECORD', 'relation' => null, 'children' => [
            ['kind' => 'condition', 'field' => 'bankAccounts.label', 'operator' => 'CONTAINS', 'value' => 'BTG', 'negated' => false],
            ['kind' => 'condition', 'field' => 'bankAccounts.accountType', 'operator' => 'EQUALS', 'value' => 'INVESTMENT', 'negated' => false],
        ]];
        $same = [...$any, 'matchMode' => 'SAME_RECORD', 'relation' => 'bankAccounts'];

        $service = app(PersonQueryService::class);
        $anyResult = $service->lazy(DB::connection(), null, $any, null, PersonStatus::ACTIVE, [['column' => 'displayName', 'direction' => 'asc']], 0, 100);
        $sameResult = $service->lazy(DB::connection(), null, $same, null, PersonStatus::ACTIVE, [['column' => 'displayName', 'direction' => 'asc']], 0, 100);

        $this->assertSame([1, 3], $anyResult['people']->pluck('id')->all());
        $this->assertSame([3], $sameResult['people']->pluck('id')->all());
    }

    public function test_an_explicit_status_filter_replaces_the_default_active_status(): void
    {
        $filter = ['kind' => 'group', 'combinator' => 'AND', 'negated' => false, 'matchMode' => 'ANY_RECORD', 'relation' => null, 'children' => [
            ['kind' => 'condition', 'field' => 'status', 'operator' => 'EQUALS', 'value' => 'INACTIVE', 'negated' => false],
        ]];

        $result = app(PersonQueryService::class)->lazy(DB::connection(), null, $filter, null, PersonStatus::ACTIVE, [['column' => 'displayName', 'direction' => 'asc']], 0, 100);

        $this->assertSame([2], $result['people']->pluck('id')->all());
    }
}
