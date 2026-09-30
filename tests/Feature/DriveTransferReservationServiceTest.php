<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferReservationSide;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\User;
use App\Services\FileStorage\Drive\DriveTransferReservationService;
use App\Services\FileStorage\Drive\DriveWorkspaceCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveTransferReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reserves_overlapping_branches_and_allows_an_unrelated_branch(): void
    {
        $user = User::factory()->create();
        $source = $this->folder($user, 'Source');
        $descendant = $this->folder($user, 'Descendant', $source->id);
        $other = $this->folder($user, 'Other');
        $target = DriveWorkspaceTarget::personal($user->id);
        $service = app(DriveTransferReservationService::class);
        $service->acquire($this->transfer($user),
            ['target' => $target, 'rootFolderId' => $source->id, 'side' => WorkspaceTransferReservationSide::Source],
            ['target' => $target, 'rootFolderId' => $other->id, 'side' => WorkspaceTransferReservationSide::Destination]);

        $this->expectExceptionMessage('DRIVE_TRANSFER_IN_PROGRESS');
        $service->assertMutationAvailable($target, $descendant->id);
    }

    public function test_it_rejects_a_second_overlapping_reservation_but_keeps_unrelated_branches_available(): void
    {
        $user = User::factory()->create();
        $first = $this->folder($user, 'First');
        $second = $this->folder($user, 'Second');
        $third = $this->folder($user, 'Third');
        $target = DriveWorkspaceTarget::personal($user->id);
        $service = app(DriveTransferReservationService::class);
        $service->acquire($this->transfer($user),
            ['target' => $target, 'rootFolderId' => $first->id, 'side' => WorkspaceTransferReservationSide::Source],
            ['target' => $target, 'rootFolderId' => $second->id, 'side' => WorkspaceTransferReservationSide::Destination]);
        $service->assertMutationAvailable($target, $third->id);

        $this->expectExceptionMessage('DRIVE_TRANSFER_IN_PROGRESS');
        $service->acquire($this->transfer($user),
            ['target' => $target, 'rootFolderId' => $first->id, 'side' => WorkspaceTransferReservationSide::Source],
            ['target' => $target, 'rootFolderId' => $third->id, 'side' => WorkspaceTransferReservationSide::Destination]);
    }

    public function test_it_blocks_existing_drive_mutations_inside_a_reserved_branch(): void
    {
        $user = User::factory()->create();
        $reserved = $this->folder($user, 'Reserved');
        $target = DriveWorkspaceTarget::personal($user->id);
        app(DriveTransferReservationService::class)->acquire($this->transfer($user),
            ['target' => $target, 'rootFolderId' => $reserved->id, 'side' => WorkspaceTransferReservationSide::Source],
            ['target' => $target, 'rootFolderId' => null, 'side' => WorkspaceTransferReservationSide::Destination]);

        $this->expectExceptionMessage('DRIVE_TRANSFER_IN_PROGRESS');
        app(DriveWorkspaceCommandService::class)->createFolder($user, $target, 'Blocked', $reserved->id);
    }

    private function folder(User $user, string $name, ?int $parent = null): WorkspaceFolder
    {
        return WorkspaceFolder::query()->create(['idUser' => $user->id, 'idParentFolder' => $parent, 'displayName' => $name, 'state' => 'ACTIVE']);
    }

    private function transfer(User $user): WorkspaceTransfer
    {
        return WorkspaceTransfer::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'sourceScope' => 'PERSONAL', 'destinationScope' => 'PERSONAL', 'mode' => 'COPY',
            'selectionManifest' => [['type' => 'folder', 'id' => 1]], 'state' => 'PENDING', 'totalItems' => 1,
            'leaseExpiresAt' => now()->addMinutes(10),
        ]);
    }
}
