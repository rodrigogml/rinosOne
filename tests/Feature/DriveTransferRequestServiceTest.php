<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\FileStorage\WorkspaceTransferReservation;
use App\Models\User;
use App\Services\FileStorage\Drive\DriveTransferRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DriveTransferRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_request_and_all_branch_reservations_together(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $source = $this->folder($user, 'Source');
        $destination = $this->folder($user, 'Destination');
        $transfer = app(DriveTransferRequestService::class)->request(
            $user,
            DriveWorkspaceTarget::personal($user->id),
            DriveWorkspaceTarget::personal($user->id),
            [['type' => 'folder', 'id' => $source->id]],
            WorkspaceTransferMode::Copy,
            $destination->id,
            (string) str()->uuid(),
            'test-transfer',
        );

        $this->assertDatabaseHas('file_workspaceTransfer', ['id' => $transfer->id, 'totalItems' => 1, 'state' => 'PENDING']);
        $this->assertSame(2, WorkspaceTransferReservation::query()->where('idWorkspaceTransfer', $transfer->id)->count());
    }

    public function test_it_rolls_back_the_transfer_when_a_branch_is_already_reserved(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $source = $this->folder($user, 'Source');
        $destination = $this->folder($user, 'Destination');
        $service = app(DriveTransferRequestService::class);
        $service->request($user, DriveWorkspaceTarget::personal($user->id), DriveWorkspaceTarget::personal($user->id), [['type' => 'folder', 'id' => $source->id]], WorkspaceTransferMode::Copy, $destination->id);

        $this->expectException(DriveWorkspaceCommandException::class);
        try {
            $service->request($user, DriveWorkspaceTarget::personal($user->id), DriveWorkspaceTarget::personal($user->id), [['type' => 'folder', 'id' => $source->id]], WorkspaceTransferMode::Copy, $destination->id);
        } finally {
            $this->assertSame(1, WorkspaceTransfer::query()->count());
        }
    }

    private function folder(User $user, string $name): WorkspaceFolder
    {
        return WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => $name, 'state' => 'ACTIVE']);
    }
}
