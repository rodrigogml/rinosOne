<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\FileStorage\Drive\DriveTransferValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveTransferValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_one_readable_source_and_an_authorized_destination(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Source');
        $destination = $this->folder($user, 'Destination');

        app(DriveTransferValidationService::class)->validate($user, DriveWorkspaceTarget::personal($user->id), DriveWorkspaceTarget::personal($user->id), [
            ['type' => 'folder', 'id' => $folder->id],
        ], WorkspaceTransferMode::Copy, $destination->id);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_invalid_selection_configuration_and_an_excessive_tree(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Source');
        $destination = $this->folder($user, 'Destination');
        $service = app(DriveTransferValidationService::class);
        config(['file-storage.workspaceTransfer.maximumItems' => 1]);
        try {
            $service->validate($user, DriveWorkspaceTarget::personal($user->id), DriveWorkspaceTarget::personal($user->id), [
                ['type' => 'folder', 'id' => $folder->id], ['type' => 'folder', 'id' => $folder->id],
            ], WorkspaceTransferMode::Copy, $destination->id);
            $this->fail('A duplicate selection must be rejected.');
        } catch (DriveWorkspaceCommandException $exception) {
            $this->assertSame('DRIVE_TRANSFER_LIMIT_EXCEEDED', $exception->reasonCode);
        }

        config(['file-storage.workspaceTransfer.maximumItems' => 10, 'file-storage.workspaceTransfer.maximumTreeDepth' => 1]);
        $child = $this->folder($user, 'Child', $folder->id);
        $this->folder($user, 'Grandchild', $child->id);
        $this->expectException(DriveWorkspaceCommandException::class);
        $this->expectExceptionMessage('DRIVE_TRANSFER_LIMIT_EXCEEDED');
        $service->validate($user, DriveWorkspaceTarget::personal($user->id), DriveWorkspaceTarget::personal($user->id), [['type' => 'folder', 'id' => $folder->id]], WorkspaceTransferMode::Move, $destination->id);
    }

    private function folder(User $user, string $displayName, ?int $parentId = null): WorkspaceFolder
    {
        return WorkspaceFolder::query()->create(['idUser' => $user->id, 'idParentFolder' => $parentId, 'displayName' => $displayName, 'state' => 'ACTIVE']);
    }
}
