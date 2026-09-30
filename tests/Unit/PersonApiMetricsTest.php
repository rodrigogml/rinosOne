<?php

namespace Tests\Unit;

use App\Services\Person\PersonApiMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonApiMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_latency_and_outcome_without_using_request_identifiers(): void
    {
        $metrics = app(PersonApiMetrics::class);
        foreach (range(1, 19) as $_) {
            $metrics->record('people.index', 200, 120);
        }
        $metrics->record('people.index', 409, 2300, 'PERSON_VERSION_CONFLICT');
        $metrics->record('people.index', 429, 10);

        $summary = $metrics->summary('people.index');

        $this->assertSame(21, $summary['requestCount']);
        $this->assertSame(2, $summary['errorCount']);
        $this->assertSame(1, $summary['rateLimitedCount']);
        $this->assertSame(1, $summary['versionConflictCount']);
        $this->assertSame(250, $summary['p95Milliseconds']);
    }

    public function test_it_activates_the_configured_p95_alert_for_the_full_window(): void
    {
        config([
            'person.performanceAlertP95Milliseconds' => 2000,
            'person.performanceAlertWindowMinutes' => 5,
        ]);
        $metrics = app(PersonApiMetrics::class);
        $metrics->record('people.index', 200, 2100);

        $this->assertTrue($metrics->alertIsActive('people.index'));
        $this->assertFalse($metrics->alertIsActive('people.show'));
    }
}
