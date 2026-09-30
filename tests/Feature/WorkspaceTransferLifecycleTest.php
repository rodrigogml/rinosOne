<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Drive\WorkspaceTransferReservationSide;
use App\Domain\FileStorage\Drive\WorkspaceTransferState;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\FileStorage\WorkspaceTransferReservation;
use App\Models\User;
use App\Services\FileStorage\Drive\WorkspaceTransferLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class WorkspaceTransferLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transitions_a_transfer_renews_its_reservations_and_releases_them_at_completion(): void
    {
        config(['file-storage.workspaceTransfer.leaseMinutes' => 10]);
        $transfer = $this->transfer();
        $reservation = WorkspaceTransferReservation::query()->create([
            'idWorkspaceTransfer' => $transfer->id,
            'side' => WorkspaceTransferReservationSide::Source,
            'workspaceScope' => 'PERSONAL',
            'leaseExpiresAt' => now()->addMinute(),
        ]);
        $service = app(WorkspaceTransferLifecycleService::class);
        $started = $service->begin($transfer, Carbon::parse('2026-09-30 12:00:00'));

        $this->assertSame(WorkspaceTransferState::Processing, $started->state);
        $this->assertTrue($started->leaseExpiresAt->equalTo('2026-09-30 12:10:00'));
        $this->assertTrue($reservation->refresh()->leaseExpiresAt->equalTo('2026-09-30 12:10:00'));

        $renewed = $service->renewLease($started, Carbon::parse('2026-09-30 12:05:00'));
        $this->assertTrue($renewed->leaseExpiresAt->equalTo('2026-09-30 12:15:00'));

        $completed = $service->complete($renewed, Carbon::parse('2026-09-30 12:06:00'));
        $this->assertSame(WorkspaceTransferState::Completed, $completed->state);
        $this->assertSame(0, WorkspaceTransferReservation::query()->count());
        $this->assertNull($completed->leaseExpiresAt);
    }

    public function test_it_cancels_only_pending_transfers_and_idempotently_fails_expired_ones(): void
    {
        $service = app(WorkspaceTransferLifecycleService::class);
        $pending = $this->transfer();
        WorkspaceTransferReservation::query()->create([
            'idWorkspaceTransfer' => $pending->id,
            'side' => WorkspaceTransferReservationSide::Destination,
            'workspaceScope' => 'PERSONAL',
            'leaseExpiresAt' => now()->addMinute(),
        ]);
        $cancelled = $service->cancel($pending);
        $this->assertSame(WorkspaceTransferState::Cancelled, $cancelled->state);
        $this->assertSame(0, WorkspaceTransferReservation::query()->count());

        $expired = $this->transfer(['state' => WorkspaceTransferState::Processing, 'leaseExpiresAt' => now()->subMinute()]);
        WorkspaceTransferReservation::query()->create([
            'idWorkspaceTransfer' => $expired->id,
            'side' => WorkspaceTransferReservationSide::Source,
            'workspaceScope' => 'PERSONAL',
            'leaseExpiresAt' => now()->subMinute(),
        ]);
        $this->assertSame(1, $service->failExpired());
        $this->assertSame(WorkspaceTransferState::Failed, $expired->refresh()->state);
        $this->assertSame('DRIVE_TRANSFER_STALE', $expired->failureCode);
        $this->assertSame(0, WorkspaceTransferReservation::query()->count());
        $this->assertSame(0, $service->failExpired());

        $this->expectException(LogicException::class);
        $service->cancel($expired);
    }

    public function test_it_rejects_an_invalid_lease_configuration_without_changing_state(): void
    {
        config(['file-storage.workspaceTransfer.leaseMinutes' => 0]);
        $transfer = $this->transfer();

        $this->expectException(LogicException::class);
        app(WorkspaceTransferLifecycleService::class)->begin($transfer);
    }

    /** @param array<string, mixed> $overrides */
    private function transfer(array $overrides = []): WorkspaceTransfer
    {
        return WorkspaceTransfer::query()->create(array_replace([
            'publicId' => (string) str()->ulid(),
            'idRequestingUser' => User::factory()->create()->id,
            'sourceScope' => 'PERSONAL',
            'destinationScope' => 'PERSONAL',
            'mode' => WorkspaceTransferMode::Copy,
            'selectionManifest' => [['type' => 'file', 'id' => 1]],
            'state' => WorkspaceTransferState::Pending,
            'totalItems' => 1,
            'processedItems' => 0,
        ], $overrides));
    }
}
