<?php

namespace App\Models;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Models\Tenant\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends TenantScopedModel
{
    use HasFactory;

    protected $table = 'person';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'personType',
        'name',
        'alias',
        'displayName',
        'cpf',
        'cnpj',
        'rg',
        'rgIssuer',
        'pisNis',
        'passportNumber',
        'foreignDocumentNumber',
        'birthDate',
        'foundationDate',
        'notes',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'personType' => PersonType::class,
            'birthDate' => 'date',
            'foundationDate' => 'date',
            'status' => PersonStatus::class,
            'version' => 'integer',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PersonAddress::class, 'idPerson');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PersonContact::class, 'idPerson');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PersonBankAccount::class, 'idPerson');
    }

    public function pixKeys(): HasMany
    {
        return $this->hasMany(PersonPixKey::class, 'idPerson');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(PersonRelationship::class, 'idSourcePerson');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(PersonRelationship::class, 'idTargetPerson');
    }
}
