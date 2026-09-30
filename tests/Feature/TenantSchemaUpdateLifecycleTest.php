<?php

namespace Tests\Feature;

use App\Domain\Tenant\SchemaUpdate\SchemaUpdateFailureClassification;
use App\Domain\Tenant\SchemaUpdate\TenantSchemaUpdateFailure;
use App\Domain\Tenant\TenantSchemaUpdateState;
use App\Models\TenantSchemaUpdate;
use App\Models\User;
use App\Services\Tenant\TenantSchemaUpdateLifecycle;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantSchemaUpdateLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_update_lifecycle_migration_defines_the_required_core_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tenantSchemaUpdate'));
        $this->assertEqualsCanonicalizing([
            'id', 'idTenant', 'targetCatalog', 'state', 'attemptCount', 'lastFailureCode',
            'startedAt', 'completedAt', 'createdAt', 'updatedAt',
        ], Schema::getColumnListing('tenantSchemaUpdate'));
    }

    public function test_it_creates_or_reuses_one_update_for_the_same_tenant_and_catalog(): void
    {
        $tenantId = $this->createTenant();
        $lifecycle = app(TenantSchemaUpdateLifecycle::class);

        $first = $lifecycle->queue((string) $tenantId, 'catalog-v1');
        $second = $lifecycle->queue((string) $tenantId, 'catalog-v1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(TenantSchemaUpdateState::Queued, $first->state);
        $this->assertDatabaseCount('tenantSchemaUpdate', 1);
    }

    public function test_it_claims_only_one_attempt_and_marks_a_successful_update_complete(): void
    {
        $update = app(TenantSchemaUpdateLifecycle::class)->queue((string) $this->createTenant(), 'catalog-v1');
        $lifecycle = app(TenantSchemaUpdateLifecycle::class);

        $attempt = $lifecycle->claim((string) $update->id);

        $this->assertNotNull($attempt);
        $this->assertSame(1, $attempt->attemptCount);
        $this->assertNull($lifecycle->claim((string) $update->id));
        $this->assertTrue($lifecycle->succeed($attempt));
        $this->assertFalse($lifecycle->succeed($attempt));
        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Succeeded->value,
            'attemptCount' => 1,
            'lastFailureCode' => null,
        ]);
    }

    public function test_it_requeues_a_transient_failure_using_the_shared_retry_policy(): void
    {
        $update = app(TenantSchemaUpdateLifecycle::class)->queue((string) $this->createTenant(), 'catalog-v1');
        $lifecycle = app(TenantSchemaUpdateLifecycle::class);
        $attempt = $lifecycle->claim((string) $update->id);

        $this->assertNotNull($attempt);
        $this->assertSame(60, $lifecycle->fail($attempt, new TenantSchemaUpdateFailure(
            SchemaUpdateFailureClassification::Transient,
            'TENANT_SCHEMA_UPDATE_DATABASE_TRANSIENT',
        )));
        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Queued->value,
            'attemptCount' => 1,
            'lastFailureCode' => 'TENANT_SCHEMA_UPDATE_DATABASE_TRANSIENT',
            'startedAt' => null,
        ]);
    }

    public function test_it_marks_a_terminal_failure_complete_without_persisting_technical_messages(): void
    {
        $update = app(TenantSchemaUpdateLifecycle::class)->queue((string) $this->createTenant(), 'catalog-v1');
        $lifecycle = app(TenantSchemaUpdateLifecycle::class);
        $attempt = $lifecycle->claim((string) $update->id);

        $this->assertNotNull($attempt);
        $this->assertNull($lifecycle->fail($attempt, new TenantSchemaUpdateFailure(
            SchemaUpdateFailureClassification::Terminal,
            'TENANT_SCHEMA_UPDATE_CATALOG_INVALID',
        )));
        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Failed->value,
            'lastFailureCode' => 'TENANT_SCHEMA_UPDATE_CATALOG_INVALID',
        ]);
        $this->assertNotNull(TenantSchemaUpdate::query()->findOrFail($update->id)->completedAt);
    }

    public function test_it_marks_a_queued_update_failed_when_attempts_are_already_exhausted(): void
    {
        config()->set('access.tenantProvisioning.maximumAttempts', 1);
        $update = app(TenantSchemaUpdateLifecycle::class)->queue((string) $this->createTenant(), 'catalog-v1');
        DB::table('tenantSchemaUpdate')->where('id', $update->id)->update(['attemptCount' => 1]);

        $this->assertNull(app(TenantSchemaUpdateLifecycle::class)->claim((string) $update->id));
        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Failed->value,
            'lastFailureCode' => 'TENANT_SCHEMA_UPDATE_ATTEMPTS_EXHAUSTED',
        ]);
    }

    public function test_it_refuses_failure_values_that_could_persist_technical_error_messages(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TenantSchemaUpdateFailure(
            SchemaUpdateFailureClassification::Terminal,
            'Connection unavailable at database host',
        );
    }

    public function test_database_constraints_prevent_duplicate_targets_and_cascade_deleted_tenants(): void
    {
        $tenantId = $this->createTenant();
        app(TenantSchemaUpdateLifecycle::class)->queue((string) $tenantId, 'catalog-v1');

        try {
            DB::table('tenantSchemaUpdate')->insert([
                'idTenant' => $tenantId,
                'targetCatalog' => 'catalog-v1',
                'state' => TenantSchemaUpdateState::Queued->value,
                'attemptCount' => 0,
            ]);
            $this->fail('Expected the tenant and catalog uniqueness constraint.');
        } catch (QueryException) {
            $this->assertDatabaseCount('tenantSchemaUpdate', 1);
        }

        DB::table('tenant')->where('id', $tenantId)->delete();

        $this->assertDatabaseCount('tenantSchemaUpdate', 0);
    }

    public function test_it_purges_only_expired_completed_evidence_using_the_configured_retention(): void
    {
        Carbon::setTestNow('2026-09-30 12:00:00');
        config()->set('schema-compatibility.tenantUpdateRetentionDays', 90);
        $tenantId = $this->createTenant();
        $this->insertUpdate($tenantId, 'expired', TenantSchemaUpdateState::Succeeded, now()->subDays(91));
        $this->insertUpdate($tenantId, 'recent', TenantSchemaUpdateState::Failed, now()->subDays(89));
        $this->insertUpdate($tenantId, 'active', TenantSchemaUpdateState::Queued, null);

        $this->assertSame(1, app(TenantSchemaUpdateLifecycle::class)->purgeExpired());
        $this->assertDatabaseMissing('tenantSchemaUpdate', ['targetCatalog' => 'expired']);
        $this->assertDatabaseHas('tenantSchemaUpdate', ['targetCatalog' => 'recent']);
        $this->assertDatabaseHas('tenantSchemaUpdate', ['targetCatalog' => 'active']);
    }

    private function createTenant(): int
    {
        $user = User::factory()->create();
        DB::table('tenant')->insert([
            'displayName' => 'Schema update tenant',
            'state' => 'ACTIVE',
        ]);
        $tenantId = (int) DB::table('tenant')->max('id');
        DB::table('tenantMembership')->insert([
            'idTenant' => $tenantId,
            'idUser' => $user->id,
            'state' => 'ACTIVE',
        ]);

        return $tenantId;
    }

    private function insertUpdate(int $tenantId, string $targetCatalog, TenantSchemaUpdateState $state, ?\DateTimeInterface $completedAt): void
    {
        DB::table('tenantSchemaUpdate')->insert([
            'idTenant' => $tenantId,
            'targetCatalog' => $targetCatalog,
            'state' => $state->value,
            'attemptCount' => 0,
            'completedAt' => $completedAt,
        ]);
    }
}
