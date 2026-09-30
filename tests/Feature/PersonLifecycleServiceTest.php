<?php

namespace Tests\Feature;

use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonStatus;
use App\Services\Person\PersonLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonLifecycleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('person', function ($table): void {
            $table->id();
            $table->string('status');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
        Schema::create('personAuditEvent', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->string('action', 16);
            $table->timestamp('occurredAt');
            $table->string('correlationId', 128)->nullable();
        });
        DB::table('person')->insert(['id' => 1, 'status' => 'ACTIVE', 'version' => 1]);
    }

    public function test_transitions_are_idempotent_and_preserve_the_person(): void
    {
        $service = app(PersonLifecycleService::class);
        $inactive = $service->inactivate(DB::connection(), 1, actorUserId: 9, correlationId: 'people-lifecycle-42');
        $again = $service->inactivate(DB::connection(), 1);
        $active = $service->reactivate(DB::connection(), 1);
        $this->assertSame(PersonStatus::INACTIVE, $inactive->status);
        $this->assertSame(2, $again->version);
        $this->assertSame(PersonStatus::ACTIVE, $active->status);
        $this->assertSame(3, $active->version);
        $this->assertDatabaseCount('personAuditEvent', 2);
        $this->assertDatabaseHas('personAuditEvent', ['action' => 'INACTIVATED', 'idActorUser' => 9, 'correlationId' => 'people-lifecycle-42']);
        $this->assertDatabaseHas('personAuditEvent', ['action' => 'REACTIVATED']);
    }

    public function test_it_rejects_a_stale_version_before_changing_the_lifecycle_state(): void
    {
        $this->expectException(PersonVersionConflictException::class);
        app(PersonLifecycleService::class)->inactivate(DB::connection(), 1, expectedVersion: 2);
    }
}
