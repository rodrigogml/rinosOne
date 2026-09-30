<?php

namespace App\Models\FileStorage;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceExport extends Model
{
    protected $table = 'file_workspaceExport';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'publicId', 'idRequestingUser', 'idTenant', 'workspaceScope', 'selectionManifest', 'state', 'displayName', 'storageKey', 'storedSizeBytes', 'expiresAt', 'failureCode',
    ];

    protected function casts(): array
    {
        return ['selectionManifest' => 'array', 'storedSizeBytes' => 'integer', 'expiresAt' => 'datetime'];
    }

    public function requestingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idRequestingUser');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'idTenant');
    }
}
