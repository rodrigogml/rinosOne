<?php

namespace App\Console\Commands;

use App\Services\Maintenance\EconomicIndicatorMaintenanceService;
use Illuminate\Console\Command;

class SynchronizeEconomicIndicators extends Command
{
    protected $signature = 'maintenance:economic-indicators:sync
                            {--series=* : Restrict this process execution to one or more catalog series codes}';

    protected $description = 'Trigger the managed economic indicator synchronization and persist its technical history.';

    public function handle(EconomicIndicatorMaintenanceService $maintenance): int
    {
        /** @var list<string> $series */
        $series = array_values(array_filter(array_map(
            static fn (string $code): string => trim($code),
            $this->option('series'),
        )));

        if ($series !== []) {
            config()->set('economic-indicators.enabled_series', $series);
        }

        $history = $maintenance->synchronizeScheduled();

        if ($history === null) {
            $this->components->warn('Economic indicator synchronization is already running.');

            return self::FAILURE;
        }

        $this->components->info("Economic indicator synchronization {$history->state} (execution {$history->id}).");

        return $history->state === 'SUCCEEDED' ? self::SUCCESS : self::FAILURE;
    }
}
