<?php

namespace Tests\Feature;

use App\Models\AuthorizationAuditEvent;
use App\Services\Authorization\AuthorizationAuditRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_only_events_outside_the_default_retention_period(): void
    {
        $now = CarbonImmutable::parse('2026-09-26 12:00:00');
        $expired = $this->eventAt($now->subDays(90));
        $retained = $this->eventAt($now->subDays(90)->addSecond());

        $deleted = app(AuthorizationAuditRetentionService::class)->purgeExpired($now);

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($expired);
        $this->assertModelExists($retained);
    }

    public function test_it_uses_an_environment_overridden_retention_period(): void
    {
        config(['authorization.auditRetentionDays' => 30]);
        $now = CarbonImmutable::parse('2026-09-26 12:00:00');
        $expired = $this->eventAt($now->subDays(30));
        $retained = $this->eventAt($now->subDays(30)->addSecond());

        $deleted = app(AuthorizationAuditRetentionService::class)->purgeExpired($now);

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($expired);
        $this->assertModelExists($retained);
    }

    public function test_the_command_fails_without_deleting_events_when_the_retention_is_invalid(): void
    {
        config(['authorization.auditRetentionDays' => 0]);
        $event = $this->eventAt(CarbonImmutable::parse('2020-01-01 00:00:00'));

        $this->artisan('authorization:purge-audit-events')
            ->assertFailed();

        $this->assertModelExists($event);
    }

    private function eventAt(CarbonImmutable $occurredAt): AuthorizationAuditEvent
    {
        return AuthorizationAuditEvent::query()->create([
            'occurredAt' => $occurredAt,
            'operation' => 'authorization.test.changed',
            'targetType' => 'authorization.test',
            'targetId' => 42,
        ]);
    }
}
