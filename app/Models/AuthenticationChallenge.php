<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuthenticationChallenge extends Model
{
    use HasFactory;

    protected $table = 'authenticationChallenge';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'idUser',
        'purpose',
        'secretHash',
        'codeHash',
        'expiresAt',
        'consumedAt',
        'supersededAt',
        'failedAttempts',
        'rememberMeRequested',
    ];

    protected function casts(): array
    {
        return [
            'expiresAt' => 'datetime',
            'consumedAt' => 'datetime',
            'supersededAt' => 'datetime',
            'failedAttempts' => 'integer',
            'rememberMeRequested' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    protected static function booted(): void
    {
        static::creating(function (self $challenge): void {
            $challenge->id ??= (string) Str::ulid();
        });
    }
}
