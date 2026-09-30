<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Drive\WorkspaceTransferReservationSide;
use App\Domain\FileStorage\Drive\WorkspaceTransferState;
use App\Jobs\FileStorage\ProcessWorkspaceTransfer;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\FileStorage\WorkspaceTransferReservation;
use App\Models\User;
use App\Services\FileStorage\Drive\WorkspaceTransferRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkspaceTransferRecoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requeues_an_expired_transfer_below_the_attempt_limit(): void
    {
        Queue::fake();
        config(['file-storage.workspaceTransfer.maximumAttempts' => 3]);
        $transfer = $this->transfer(1);
        WorkspaceTransferReservation::query()->create(['idWorkspaceTransfer' => $transfer->id, 'side' => WorkspaceTransferReservationSide::Source, 'workspaceScope' => 'PERSONAL', 'leaseExpiresAt' => now()->subMinute()]);

        $this->assertSame(1, app(WorkspaceTransferRecoveryService::class)->recoverExpired());
        $this->assertSame(WorkspaceTransferState::Pending, $transfer->refresh()->state);
        $this->assertTrue($transfer->leaseExpiresAt->isFuture());
        Queue::assertPushed(ProcessWorkspaceTransfer::class, fn (ProcessWorkspaceTransfer $job): bool => $job->transferId === $transfer->publicId);
    }

    public function test_it_fails_and_releases_an_expired_transfer_at_the_attempt_limit(): void
    {
        Queue::fake();
        config(['file-storage.workspaceTransfer.maximumAttempts' => 3]);
        $transfer = $this->transfer(3);
        WorkspaceTransferReservation::query()->create(['idWorkspaceTransfer' => $transfer->id, 'side' => WorkspaceTransferReservationSide::Source, 'workspaceScope' => 'PERSONAL', 'leaseExpiresAt' => now()->subMinute()]);

        $this->assertSame(1, app(WorkspaceTransferRecoveryService::class)->recoverExpired());
        $this->assertSame(WorkspaceTransferState::Failed, $transfer->refresh()->state);
        $this->assertSame('DRIVE_TRANSFER_STALE', $transfer->failureCode);
        $this->assertSame(0, WorkspaceTransferReservation::query()->count());
        Queue::assertNothingPushed();
    }

    private function transfer(int $attempts): WorkspaceTransfer
    {
        return WorkspaceTransfer::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => User::factory()->create()->id,
            'sourceScope' => 'PERSONAL', 'sourceUserId' => 1, 'destinationScope' => 'PERSONAL', 'destinationUserId' => 1,
            'mode' => WorkspaceTransferMode::Copy, 'selectionManifest' => [['type' => 'file', 'id' => 1]],
            'state' => WorkspaceTransferState::Processing, 'totalItems' => 1, 'attemptCount' => $attempts, 'leaseExpiresAt' => now()->subMinute(),
        ]);
    }
}
