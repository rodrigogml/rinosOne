<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonPixKeyInput;
use App\Domain\Person\PersonPixKeyType;
use App\Services\Person\PersonPixKeyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonPixKeyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->timestamps();
        });
        Schema::create('personPixKey', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('keyType');
            $table->string('keyValue');
            $table->string('normalizedKeyValue');
            $table->string('status');
            $table->unique(['idPerson', 'keyType', 'normalizedKeyValue']);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        DB::table('person')->insert(['id' => 1]);
    }

    public function test_it_rejects_the_same_normalized_key_for_one_person(): void
    {
        $service = app(PersonPixKeyService::class);
        $service->create(DB::connection(), 1, new PersonPixKeyInput(PersonPixKeyType::EMAIL, 'User@example.test'));
        $this->expectException(PersonValidationException::class);
        $service->create(DB::connection(), 1, new PersonPixKeyInput(PersonPixKeyType::EMAIL, 'user@example.test'));
    }
}
