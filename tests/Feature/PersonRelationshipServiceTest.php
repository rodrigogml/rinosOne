<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonRelationshipType;
use App\Services\Person\PersonRelationshipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonRelationshipServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->string('personType');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personRelationship', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idSourcePerson');
            $table->unsignedBigInteger('idTargetPerson');
            $table->string('relationshipType', 32);
            $table->string('description', 1000)->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->unique(['idSourcePerson', 'idTargetPerson', 'relationshipType'], 'uk_person_relationship_direction_type');
        });
        DB::table('person')->insert([
            ['id' => 1, 'personType' => 'PF'],
            ['id' => 2, 'personType' => 'PF'],
            ['id' => 3, 'personType' => 'PJ'],
            ['id' => 4, 'personType' => 'PJ'],
        ]);
    }

    public function test_it_creates_one_directional_relationship_and_removes_only_that_relationship(): void
    {
        $service = app(PersonRelationshipService::class);
        $relationship = $service->create(DB::connection(), 1, 2, PersonRelationshipType::CHILD_OF, ' Family ');

        $this->assertDatabaseHas('personRelationship', ['id' => $relationship->id, 'idSourcePerson' => 1, 'idTargetPerson' => 2, 'relationshipType' => 'CHILD_OF', 'description' => 'Family']);
        $this->assertDatabaseCount('personRelationship', 1);
        $service->remove(DB::connection(), $relationship->id);
        $this->assertDatabaseCount('personRelationship', 0);
        $this->assertDatabaseCount('person', 4);
    }

    public function test_it_rejects_self_relationships_and_duplicate_directional_rows(): void
    {
        $service = app(PersonRelationshipService::class);
        $this->expectException(PersonValidationException::class);
        try {
            $service->create(DB::connection(), 1, 1, PersonRelationshipType::OTHER);
        } finally {
            $service->create(DB::connection(), 1, 2, PersonRelationshipType::OTHER);
            $this->expectException(PersonValidationException::class);
            $service->create(DB::connection(), 1, 2, PersonRelationshipType::OTHER);
        }
    }

    public function test_it_projects_the_inverse_type_without_creating_a_second_row_and_allows_editing(): void
    {
        $service = app(PersonRelationshipService::class);
        $relationship = $service->create(DB::connection(), 1, 2, PersonRelationshipType::CHILD_OF);
        $updated = $service->update(DB::connection(), $relationship->id, PersonRelationshipType::GRANDCHILD_OF, 'updated');

        $this->assertSame(PersonRelationshipType::GRANDCHILD_OF, $service->forPerson(DB::connection(), 1)[0]['relationshipType']);
        $targetProjection = $service->forPerson(DB::connection(), 2)[0];
        $this->assertSame('INCOMING', $targetProjection['direction']);
        $this->assertSame(PersonRelationshipType::GRANDPARENT_OF, $targetProjection['relationshipType']);
        $this->assertSame('updated', $updated->description);
        $this->assertDatabaseCount('personRelationship', 1);
    }

    public function test_it_supports_all_person_type_pairs_and_keeps_other_semantically_neutral(): void
    {
        $service = app(PersonRelationshipService::class);

        $service->create(DB::connection(), 1, 2, PersonRelationshipType::CHILD_OF);
        $service->create(DB::connection(), 1, 3, PersonRelationshipType::EMPLOYEE_OF);
        $service->create(DB::connection(), 3, 2, PersonRelationshipType::CONTRACTOR_OF);
        $service->create(DB::connection(), 3, 4, PersonRelationshipType::OTHER);

        $this->assertDatabaseCount('personRelationship', 4);
        $this->assertSame(PersonRelationshipType::OTHER, $service->forPerson(DB::connection(), 4)[0]['relationshipType']);
        $this->assertSame('INCOMING', $service->forPerson(DB::connection(), 4)[0]['direction']);
    }

    public function test_it_rejects_a_person_that_exists_only_in_another_tenant_schema(): void
    {
        config()->set('database.connections.relationship_other', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('relationship_other');
        Schema::connection('relationship_other')->create('person', function ($table): void {
            $table->id();
            $table->string('personType');
        });
        DB::connection('relationship_other')->table('person')->insert(['id' => 5, 'personType' => 'PF']);

        $this->expectException(PersonValidationException::class);
        app(PersonRelationshipService::class)->create(DB::connection(), 1, 5, PersonRelationshipType::OTHER);
    }
}
