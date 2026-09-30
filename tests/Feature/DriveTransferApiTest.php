<?php

namespace Tests\Feature;

use App\Jobs\FileStorage\ProcessWorkspaceTransfer;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DriveTransferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_reads_and_cancels_an_idempotent_transfer_only_for_its_requester(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $source = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Source', 'state' => 'ACTIVE']);
        $destination = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Destination', 'state' => 'ACTIVE']);
        $payload = [
            'sourceTarget' => ['kind' => 'personal'],
            'destinationTarget' => ['kind' => 'personal', 'folderId' => $destination->id],
            'items' => [['type' => 'folder', 'id' => $source->id]],
            'mode' => 'COPY',
        ];
        $key = '65db30b4-bb59-4f1b-bc73-277642a1bd95';
        $created = $this->actingAs($user)->postJson('/api/v1/drive/transfers', $payload, ['Idempotency-Key' => $key])
            ->assertAccepted()
            ->assertJsonPath('state', 'PENDING')
            ->assertJsonPath('totalItems', 1);
        $transferId = $created->json('transferId');
        Queue::assertPushed(ProcessWorkspaceTransfer::class, fn (ProcessWorkspaceTransfer $job): bool => $job->transferId === $transferId);
        $this->actingAs($user)->postJson('/api/v1/drive/transfers', $payload, ['Idempotency-Key' => $key])->assertAccepted()->assertJsonPath('transferId', $transferId);
        $this->actingAs($other)->getJson("/api/v1/drive/transfers/{$transferId}")->assertNotFound();
        $this->actingAs($user)->getJson("/api/v1/drive/transfers/{$transferId}")->assertOk()->assertJsonPath('transferId', $transferId);
        $this->actingAs($user)->postJson("/api/v1/drive/transfers/{$transferId}/cancel")->assertOk()->assertJsonPath('state', 'CANCELLED');
    }
}
