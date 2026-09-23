<?php

namespace App\Console\Commands;

use App\Services\Access\AuthenticationChallengeLifecycleService;
use Illuminate\Console\Command;

class PurgeExpiredAuthenticationChallenges extends Command
{
    protected $signature = 'access:purge-expired-challenges';

    protected $description = 'Remove expired authentication challenges without retention.';

    public function handle(AuthenticationChallengeLifecycleService $lifecycle): int
    {
        $count = $lifecycle->discardExpired();

        $this->components->info("{$count} expired authentication challenge(s) removed.");

        return self::SUCCESS;
    }
}
