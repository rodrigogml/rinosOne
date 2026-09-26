<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantPersistenceConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_migration_catalog_includes_core_but_not_tenant_baselines(): void
    {
        $paths = app('migrator')->paths();

        $this->assertContains(database_path('migrations/core'), $paths);
        $this->assertNotContains(database_path('migrations/tenant'), $paths);
        $this->assertFileExists(database_path('migrations/tenant/0001_01_01_000000_create_tenant_migration_baseline.php'));
    }

    public function test_global_migration_command_executes_the_global_catalog_without_tenant_baselines(): void
    {
        $this->artisan('migrate:global', ['--pretend' => true])
            ->assertSuccessful();
    }

    public function test_tenant_membership_requires_existing_tenant_and_user(): void
    {
        $this->expectException(QueryException::class);

        DB::table('tenantMembership')->insert([
            'id' => 1,
            'idTenant' => 999999,
            'idUser' => 999999,
            'role' => 'OWNER',
            'state' => 'ACTIVE',
        ]);
    }

    public function test_provisioning_intent_is_unique_for_each_requesting_user(): void
    {
        $user = User::factory()->create();
        $intent = (string) str()->uuid();

        $this->createTenantWithProvisioning($user, $intent);

        $this->expectException(QueryException::class);

        $this->createTenantWithProvisioning($user, $intent);
    }

    private function createTenantWithProvisioning(User $user, string $intent): void
    {
        DB::table('tenant')->insert([
            'displayName' => 'Tenant test',
            'state' => 'PROVISIONING',
        ]);

        $tenantId = (int) DB::table('tenant')->max('id');

        DB::table('tenantProvisioning')->insert([
            'idTenant' => $tenantId,
            'idRequestedByUser' => $user->id,
            'idempotencyKey' => $intent,
            'state' => 'QUEUED',
            'attemptCount' => 0,
        ]);
    }
}
