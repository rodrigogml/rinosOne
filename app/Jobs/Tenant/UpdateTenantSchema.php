<?php

namespace App\Jobs\Tenant;

use App\Services\Tenant\TenantSchemaUpdateExecutor;
use App\Services\Tenant\TenantSchemaUpdateFailureClassifier;
use App\Services\Tenant\TenantSchemaUpdateLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateTenantSchema implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $updateId) {}

    public function uniqueId(): string
    {
        return (string) $this->updateId;
    }

    public function handle(
        TenantSchemaUpdateLifecycle $lifecycle,
        TenantSchemaUpdateExecutor $executor,
        TenantSchemaUpdateFailureClassifier $failureClassifier,
    ): void {
        $attempt = $lifecycle->claim((string) $this->updateId);

        if ($attempt === null) {
            return;
        }

        try {
            $executor->execute($attempt);
            $lifecycle->succeed($attempt);
        } catch (Throwable $exception) {
            $retryDelay = $lifecycle->fail($attempt, $failureClassifier->classify($exception));

            if ($retryDelay !== null) {
                $this->release($retryDelay);
            }
        }
    }
}
