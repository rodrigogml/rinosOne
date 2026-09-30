<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\TenantSchemaUpdateDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DiscoverTenantSchemaUpdates implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function handle(TenantSchemaUpdateDiscoveryService $discovery): void
    {
        $discovery->discover();
    }
}
