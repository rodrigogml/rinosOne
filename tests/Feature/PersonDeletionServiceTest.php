<?php

namespace Tests\Feature;

use App\Domain\Person\Deletion\PersonUsage;
use App\Domain\Person\Deletion\PersonUsageInspector;
use App\Domain\Person\Exception\PersonDeletionConflictException;
use App\Domain\Person\Exception\PersonInUseException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Services\Person\PersonAuditLogger;
use App\Services\Person\PersonDeletionService;
use App\Services\Person\PersonDeletionUsageService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonDeletionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('person', function ($table): void {
            $table->id();
            $table->string('status')->default('ACTIVE');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personContact', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->foreign('idPerson')->references('id')->on('person')->cascadeOnDelete();
        });
        Schema::create('personRelationship', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idSourcePerson');
            $table->unsignedBigInteger('idTargetPerson');
            $table->foreign('idSourcePerson')->references('id')->on('person')->cascadeOnDelete();
            $table->foreign('idTargetPerson')->references('id')->on('person')->cascadeOnDelete();
        });
        Schema::create('personAuditEvent', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->string('action', 16);
            $table->timestamp('occurredAt');
            $table->string('correlationId', 128)->nullable();
        });

        DB::table('person')->insert([
            ['id' => 1, 'status' => 'ACTIVE', 'version' => 1],
            ['id' => 2, 'status' => 'ACTIVE', 'version' => 1],
        ]);
        DB::table('personContact')->insert(['idPerson' => 1]);
        DB::table('personRelationship')->insert(['idSourcePerson' => 1, 'idTargetPerson' => 2]);
        DB::table('personAuditEvent')->insert([
            'personId' => 1,
            'action' => 'CREATED',
            'occurredAt' => now(),
        ]);
    }

    public function test_it_deletes_children_and_relationships_without_deleting_the_counterparty_or_audit_event(): void
    {
        $this->deletionService()->delete(DB::connection(), 1);

        $this->assertDatabaseMissing('person', ['id' => 1]);
        $this->assertDatabaseHas('person', ['id' => 2]);
        $this->assertDatabaseCount('personContact', 0);
        $this->assertDatabaseCount('personRelationship', 0);
        $this->assertDatabaseHas('personAuditEvent', ['personId' => 1]);
        $this->assertDatabaseHas('personAuditEvent', ['personId' => 1, 'action' => 'DELETED']);
    }

    public function test_it_preserves_the_person_and_reports_safe_known_usages(): void
    {
        $inspector = new class implements PersonUsageInspector
        {
            public function inspect(ConnectionInterface $connection, int $personId): array
            {
                return [new PersonUsage('Financeiro', 'Resolva os lançamentos que utilizam esta pessoa.')];
            }
        };
        $this->app->instance('test.person-deletion-inspector', $inspector);
        $this->app->tag('test.person-deletion-inspector', PersonDeletionUsageService::INSPECTOR_TAG);

        try {
            $this->deletionService()->delete(DB::connection(), 1);
            $this->fail('A deletion with a known usage must be blocked.');
        } catch (PersonInUseException $exception) {
            $this->assertSame('Financeiro', $exception->usages[0]->module);
        }

        $this->assertDatabaseHas('person', ['id' => 1]);
    }

    public function test_it_converts_an_unclassified_integrity_failure_to_a_safe_conflict(): void
    {
        Schema::create('externalUsage', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->foreign('idPerson')->references('id')->on('person');
        });
        DB::table('externalUsage')->insert(['idPerson' => 1]);

        $this->expectException(PersonDeletionConflictException::class);
        try {
            $this->deletionService()->delete(DB::connection(), 1);
        } finally {
            $this->assertDatabaseHas('person', ['id' => 1]);
        }
    }

    public function test_it_rejects_a_stale_version_without_deleting_the_person(): void
    {
        $this->expectException(PersonVersionConflictException::class);
        try {
            $this->deletionService()->delete(DB::connection(), 1, expectedVersion: 2);
        } finally {
            $this->assertDatabaseHas('person', ['id' => 1]);
        }
    }

    private function deletionService(): PersonDeletionService
    {
        return new PersonDeletionService(
            new PersonDeletionUsageService($this->app),
            app(PersonAuditLogger::class),
        );
    }
}
