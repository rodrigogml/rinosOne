<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateGlobalSchema extends Command
{
    protected $signature = 'migrate:global
                            {--force : Execute in production without confirmation}
                            {--pretend : Show the SQL statements without executing them}';

    protected $description = 'Run the legacy and core migration catalogs for the global schema.';

    public function handle(): int
    {
        return $this->call('migrate', [
            '--database' => 'coreMigration',
            '--force' => (bool) $this->option('force'),
            '--pretend' => (bool) $this->option('pretend'),
        ]);
    }
}
