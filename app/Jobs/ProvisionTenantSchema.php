<?php

namespace App\Jobs;

use App\Services\Tenant\TenantProvisioningFailureClassifier;
use App\Services\Tenant\TenantProvisioningLifecycle;
use App\Services\Tenant\TenantSchemaProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionTenantSchema implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $provisioningId) {}

    public function handle(
        TenantProvisioningLifecycle $lifecycle,
        TenantSchemaProvisioner $provisioner,
        TenantProvisioningFailureClassifier $failureClassifier,
    ): void {
        $attempt = $lifecycle->claim($this->provisioningId);

        if ($attempt === null) {
            return;
        }

        try {
            $provisioner->prepare($attempt->tenantId);
            $lifecycle->succeed($attempt);
        } catch (Throwable $exception) {
            $retryDelay = $lifecycle->fail($attempt, $failureClassifier->classify($exception));

            if ($retryDelay !== null) {
                $this->release($retryDelay);
            }
        }
    }
}
