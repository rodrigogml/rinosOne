<?php

namespace Tests\Feature;

use LogicException;
use Tests\TestCase;

class WorkspaceExportScheduleConfigurationTest extends TestCase
{
    public function test_it_rejects_an_invalid_export_cleanup_interval_before_registering_the_cleanup_job(): void
    {
        config()->set('file-storage.workspaceExport.cleanupIntervalMinutes', 0);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DRIVE_EXPORT_CLEANUP_INTERVAL_MINUTES must be between 1 and 60.');

        require base_path('routes/console.php');
    }
}
