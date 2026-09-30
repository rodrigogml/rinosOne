<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonBankAccountInput;
use App\Domain\Person\PersonBankAccountType;
use App\Services\Person\PersonBankAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonBankAccountServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('personBankAccount', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('label');
            $table->unsignedBigInteger('idFinancialInstitution')->nullable();
            $table->string('accountType');
            $table->string('agency')->nullable();
            $table->string('agencyDigit')->nullable();
            $table->string('accountNumber')->nullable();
            $table->string('accountDigit')->nullable();
            $table->string('status');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('financialInstitution', function ($table): void {
            $table->id();
            $table->boolean('activeForSelection');
        });
        DB::table('person')->insert(['id' => 1]);
        DB::table('financialInstitution')->insert([
            ['id' => 1, 'activeForSelection' => true],
            ['id' => 2, 'activeForSelection' => false],
        ]);
    }

    public function test_it_persists_an_account_without_a_financial_institution(): void
    {
        app(PersonBankAccountService::class)->create(DB::connection(), 1, new PersonBankAccountInput('Conta', PersonBankAccountType::CHECKING, agency: '001', accountNumber: '0002'));
        $this->assertDatabaseHas('personBankAccount', ['agency' => '001', 'accountNumber' => '0002']);
    }

    public function test_it_accepts_an_active_institution_and_rejects_an_inactive_one_for_new_accounts(): void
    {
        $service = app(PersonBankAccountService::class);
        $account = $service->create(DB::connection(), 1, new PersonBankAccountInput('Conta', PersonBankAccountType::CHECKING, 1, agency: '001', accountNumber: '0002'));

        $this->assertSame(1, $account->idFinancialInstitution);
        $this->expectException(PersonValidationException::class);
        $service->create(DB::connection(), 1, new PersonBankAccountInput('Conta inativa', PersonBankAccountType::CHECKING, 2, agency: '001', accountNumber: '0003'));
    }
}
