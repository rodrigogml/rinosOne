<?php

namespace Tests\Feature;

use App\Domain\Access\Security\SecurityEvent;
use App\Services\Access\SecurityEventLogger;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityEventLoggerTest extends TestCase
{
    public function test_security_events_use_the_dedicated_channel_with_minimal_context(): void
    {
        $logger = \Mockery::mock(Logger::class);

        Log::shouldReceive('channel')
            ->once()
            ->with('security')
            ->andReturn($logger);

        $logger->shouldReceive('info')
            ->once()
            ->with('security.access.event', [
                'event' => SecurityEvent::SessionInvalidated->value,
                'userId' => '01j00000000000000000000000',
            ]);

        app(SecurityEventLogger::class)->record(
            SecurityEvent::SessionInvalidated,
            '01j00000000000000000000000',
        );
    }

    public function test_security_log_retention_defaults_to_thirty_days(): void
    {
        $this->assertSame(30, config('logging.channels.security.days'));
    }
}
