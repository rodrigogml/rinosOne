<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Infrastructure\Authorization\Observability\AuthorizationMetrics;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthorizationMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_aggregate_decision_cache_and_batch_metrics_without_sensitive_labels(): void
    {
        Cache::flush();
        config()->set('authorization.decisionCacheSeconds', 300);
        $user = User::factory()->create();
        $authorization = app(AuthorizationService::class);

        $authorization->check($user, 'personal.file.read', AuthorizationScope::Personal);
        $authorization->check($user, 'personal.file.read', AuthorizationScope::Personal);
        $authorization->checkBatch($user, [['permissionKey' => 'missing.permission']]);

        $metrics = app(AuthorizationMetrics::class)->snapshot();

        $this->assertSame(2, $metrics['decision:deny']);
        $this->assertSame(1, $metrics['cache:hit']);
        $this->assertSame(1, $metrics['cache:miss']);
        $this->assertSame(1, $metrics['batch:count']);
        $this->assertSame(1, $metrics['batch:sizeTotal']);
        $this->assertSame(0, $metrics['failure:count']);
        $this->assertSame(['decision:allow', 'decision:deny', 'cache:hit', 'cache:miss', 'duration:totalMilliseconds', 'duration:count', 'batch:count', 'batch:sizeTotal', 'failure:count'], array_keys($metrics));

        app(AuthorizationMetrics::class)->recordFailure();

        $this->assertSame(2, app(AuthorizationMetrics::class)->snapshot()['decision:deny']);
        $this->assertSame(1, app(AuthorizationMetrics::class)->snapshot()['failure:count']);
    }
}
