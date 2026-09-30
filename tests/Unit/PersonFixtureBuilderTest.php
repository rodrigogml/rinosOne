<?php

namespace Tests\Unit;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PersonFixtureBuilder;
use Tests\TestCase;

class PersonFixtureBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_and_builder_create_only_synthetic_people_data(): void
    {
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
            $table->string('status');
            $table->unsignedBigInteger('version');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });

        Person::factory()->legalEntity()->create();
        PersonFixtureBuilder::insertPeople(app('db')->connection(), 3, firstId: 2);

        $this->assertDatabaseCount('person', 4);
        $this->assertSame('Pessoa sintética 000002', Person::query()->findOrFail(2)->name);
        $this->assertNull(Person::query()->findOrFail(2)->cpf);
        $this->assertNull(Person::query()->findOrFail(2)->cnpj);
    }
}
