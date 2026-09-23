<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PersistentAuthentication extends Model
{
    protected $table = 'persistentAuthentication';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['idUser', 'secretHash', 'lastUsedAt', 'revokedAt'];

    protected function casts(): array
    {
        return ['createdAt' => 'datetime', 'lastUsedAt' => 'datetime', 'revokedAt' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $authentication): void {
            $authentication->id ??= (string) Str::ulid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
