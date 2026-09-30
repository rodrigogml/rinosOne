<?php

namespace Tests\Performance\Authorization;

use App\Models\AuthorizationRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContextualAuthorizationAdministrationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_contextual_projections_meet_the_homologation_budget_with_one_thousand_active_subjects(): void
    {
        $administrator = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Tenant de homologação', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $administrator->id, 'state' => 'ACTIVE']);
        $administratorRole = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        DB::table('auth_role_assignment')->insert(['idRole' => $administratorRole->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $members = User::factory()->count(999)->create();
        foreach ($members->chunk(100) as $chunk) {
            TenantMembership::query()->insert($chunk->map(fn (User $member): array => [
                'idTenant' => $tenant->id,
                'idUser' => $member->id,
                'state' => 'ACTIVE',
            ])->all());
        }
        $subject = $members->firstOrFail();
        $base = "/api/v1/tenants/{$tenant->id}/authorization";

        $this->actingAs($administrator);
        $this->assertP95('context', 500, fn () => $this->getJson("{$base}/context")->assertOk());
        $this->assertP95('subjects', 500, fn () => $this->getJson("{$base}/subjects?perPage=25")->assertOk()->assertJsonPath('pagination.total', 1000));
        $this->assertP95('roles', 500, fn () => $this->getJson("{$base}/roles?perPage=25")->assertOk());
        $this->assertP95('effective access', 1000, fn () => $this->getJson("{$base}/subjects/USER/{$subject->id}/effective-access")->assertOk());
        $this->assertP95('explain', 1000, fn () => $this->postJson("{$base}/subjects/USER/{$subject->id}/explain", ['permissionKey' => 'tenant.authorization.read'])->assertOk());
    }

    private function assertP95(string $scenario, int $budgetMilliseconds, callable $operation): void
    {
        $samples = [];
        foreach (range(1, 20) as $_) {
            $startedAt = hrtime(true);
            $operation();
            $samples[] = (hrtime(true) - $startedAt) / 1_000_000;
        }
        sort($samples);
        $p95 = $samples[(int) ceil(count($samples) * 0.95) - 1];
        $this->assertLessThanOrEqual($budgetMilliseconds, $p95, "p95 excedeu o orçamento de homologação para {$scenario}: {$p95} ms.");
    }
}
