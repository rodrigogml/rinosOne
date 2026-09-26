<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersistentAuthentication extends Model
{
    protected $table = 'persistentAuthentication';

    public $timestamps = false;

    protected $fillable = ['idUser', 'secretHash', 'lastUsedAt', 'revokedAt'];

    protected function casts(): array
    {
        return ['createdAt' => 'datetime', 'lastUsedAt' => 'datetime', 'revokedAt' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
