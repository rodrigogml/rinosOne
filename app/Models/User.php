<?php

namespace App\Models;

use App\Domain\Access\Email\EmailNormalizer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'user';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'displayName',
        'passwordHash',
        'emailVerifiedAt',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'passwordHash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emailVerifiedAt' => 'datetime',
            'passwordHash' => 'hashed',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $email): string => EmailNormalizer::normalize($email),
        );
    }

    public function getAuthPasswordName(): string
    {
        return 'passwordHash';
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash ?? '';
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'idUser');
    }
}
