<?php

namespace App\Console\Commands;

use App\Services\Authorization\Advanced\AuthorizationAccessRequestService;
use Illuminate\Console\Command;
use Throwable;

class ExpireAuthorizationAccessRequests extends Command
{
    protected $signature = 'authorization:expire-access-requests';
    protected $description = 'Expires pending and approved authorization access requests whose validity ended.';

    public function handle(AuthorizationAccessRequestService $requests): int
    {
        try { $count = $requests->expireDue(); } catch (Throwable $exception) { report($exception); $this->components->error('Authorization access request expiration did not run safely.'); return self::FAILURE; }
        $this->components->info("{$count} authorization access request(s) expired.");
        return self::SUCCESS;
    }
}
