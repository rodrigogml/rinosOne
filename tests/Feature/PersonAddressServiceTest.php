<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonAddressInput;
use App\Domain\Person\PersonAddressType;
use App\Services\Person\PersonAddressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonAddressServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('personAddress', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('label');
            $table->string('addressType');
            $table->unsignedBigInteger('idCountry');
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
        Schema::create('country', function ($table): void {
            $table->id();
            $table->boolean('activeForSelection');
        });
        Schema::create('brazilState', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idCountry');
            $table->boolean('activeForSelection');
        });
        Schema::create('brazilMunicipality', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idBrazilState');
            $table->boolean('activeForSelection');
        });
        DB::table('person')->insert(['id' => 1]);
        DB::table('country')->insert(['id' => 1, 'activeForSelection' => true]);
        DB::table('brazilState')->insert(['id' => 2, 'idCountry' => 1, 'activeForSelection' => true]);
        DB::table('brazilMunicipality')->insert(['id' => 3, 'idBrazilState' => 2, 'activeForSelection' => true]);
    }

    public function test_it_persists_a_free_street_for_the_tenant_person(): void
    {
        $address = app(PersonAddressService::class)->create(DB::connection(), 1, new PersonAddressInput('Casa', PersonAddressType::RESIDENTIAL, 1, true, 2, 3, null, 'Rua Livre', 'S/N'));
        $this->assertSame(1, $address->idPerson);
        $this->assertDatabaseHas('personAddress', ['street' => 'Rua Livre', 'number' => 'S/N']);
    }

    public function test_it_rejects_a_new_address_that_references_an_inactive_catalog_entry(): void
    {
        DB::table('brazilMunicipality')->where('id', 3)->update(['activeForSelection' => false]);

        $this->expectException(PersonValidationException::class);
        app(PersonAddressService::class)->create(DB::connection(), 1, new PersonAddressInput('Casa', PersonAddressType::RESIDENTIAL, 1, true, 2, 3, null, 'Rua Livre'));
    }

    public function test_it_accepts_an_international_address_without_brazilian_territorial_references(): void
    {
        DB::table('country')->insert(['id' => 55, 'activeForSelection' => true]);

        $address = app(PersonAddressService::class)->create(DB::connection(), 1, new PersonAddressInput('Office', PersonAddressType::COMMERCIAL, 55, false, null, null, null, 'Main Street'));

        $this->assertSame(55, $address->idCountry);
        $this->assertNull($address->idBrazilState);
        $this->assertNull($address->idBrazilMunicipality);
    }
}
