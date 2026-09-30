<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Jobs\FileStorage\ProcessWorkspaceTransfer;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\FileStorage\Drive\DriveTransferRequestService;
use App\Services\FileStorage\Drive\WorkspaceTransferExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessWorkspaceTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_an_opaque_job_and_processes_the_pending_transfer_once(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $source = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Source', 'state' => 'ACTIVE']);
        $destination = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Destination', 'state' => 'ACTIVE']);
        $target = DriveWorkspaceTarget::personal($user->id);
        $transfer = app(DriveTransferRequestService::class)->request($user, $target, $target, [['type' => 'folder', 'id' => $source->id]], WorkspaceTransferMode::Copy, $destination->id);

        Queue::assertPushed(ProcessWorkspaceTransfer::class, fn (ProcessWorkspaceTransfer $job): bool => $job->transferId === $transfer->publicId);
        app(ProcessWorkspaceTransfer::class, ['transferId' => $transfer->publicId])->handle(app(WorkspaceTransferExecutionService::class));

        $this->assertSame('COMPLETED', $transfer->refresh()->state->value);
        $this->assertSame(1, $transfer->attemptCount);
    }
}
