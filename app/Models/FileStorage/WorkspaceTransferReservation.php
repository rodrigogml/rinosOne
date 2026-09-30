<?php

namespace App\Models\FileStorage;

use App\Domain\FileStorage\Drive\WorkspaceTransferReservationSide;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lease-bound branch reservation owned by one pending or processing transfer. */
class WorkspaceTransferReservation extends Model
{
    protected $table = 'file_workspaceTransferReservation';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = ['idWorkspaceTransfer', 'side', 'workspaceScope', 'idTenant', 'rootFolderId', 'leaseExpiresAt'];

    protected function casts(): array
    {
        return ['side' => WorkspaceTransferReservationSide::class, 'leaseExpiresAt' => 'datetime'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WorkspaceTransfer::class, 'idWorkspaceTransfer');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }

    public function rootFolder(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFolder::class, 'rootFolderId');
    }
}
