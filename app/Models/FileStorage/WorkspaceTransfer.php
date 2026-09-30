<?php

namespace App\Models\FileStorage;

use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Drive\WorkspaceTransferState;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persistent, asynchronous logical copy or move between two Drive workspaces. */
class WorkspaceTransfer extends Model
{
    protected $table = 'file_workspaceTransfer';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'publicId', 'idRequestingUser', 'sourceScope', 'sourceUserId', 'sourceTenantId', 'destinationScope', 'destinationUserId', 'destinationTenantId', 'destinationFolderId',
        'mode', 'selectionManifest', 'state', 'totalItems', 'processedItems', 'attemptCount', 'idempotencyKey', 'correlationId',
        'leaseExpiresAt', 'heartbeatAt', 'failureCode', 'startedAt', 'completedAt',
    ];

    protected function casts(): array
    {
        return [
            'mode' => WorkspaceTransferMode::class,
            'selectionManifest' => 'array',
            'state' => WorkspaceTransferState::class,
            'totalItems' => 'integer',
            'processedItems' => 'integer',
            'attemptCount' => 'integer',
            'leaseExpiresAt' => 'datetime',
            'heartbeatAt' => 'datetime',
            'startedAt' => 'datetime',
            'completedAt' => 'datetime',
        ];
    }

    public function requestingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idRequestingUser');
    }

    public function sourceTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'sourceTenantId');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sourceUserId');
    }

    public function destinationTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'destinationTenantId');
    }

    public function destinationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinationUserId');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(WorkspaceTransferReservation::class, 'idWorkspaceTransfer');
    }
}
