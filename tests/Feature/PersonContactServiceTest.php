<?php

namespace Tests\Feature;

use App\Domain\Person\PersonContactInput;
use App\Domain\Person\PersonContactType;
use App\Services\Person\PersonContactService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonContactServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->timestamps();
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
        DB::table('person')->insert(['id' => 1]);
    }

    public function test_it_persists_multiple_contacts_without_a_primary_marker(): void
    {
        $service = app(PersonContactService::class);
        $service->create(DB::connection(), 1, new PersonContactInput(PersonContactType::EMAIL, 'one@example.test'));
        $service->create(DB::connection(), 1, new PersonContactInput(PersonContactType::MOBILE, '+55 11 99999-9999'));

        $this->assertDatabaseCount('personContact', 2);
        $this->assertDatabaseHas('personContact', ['normalizedValue' => '5511999999999']);
    }
}
