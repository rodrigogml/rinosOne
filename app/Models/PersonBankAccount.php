<?php

namespace App\Models;

use App\Domain\Person\PersonBankAccountType;
use App\Domain\Person\PersonStatus;
use App\Models\Tenant\TenantScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonBankAccount extends TenantScopedModel
{
    protected $table = 'personBankAccount';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'idPerson', 'label', 'idFinancialInstitution', 'accountType', 'agency', 'agencyDigit',
        'accountNumber', 'accountDigit', 'status',
    ];

    protected function casts(): array
    {
        return [
            'accountType' => PersonBankAccountType::class,
            'status' => PersonStatus::class,
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPerson');
    }
}
