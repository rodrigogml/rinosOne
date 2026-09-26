<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialInstitution extends Model
{
    protected $table = 'financialInstitution';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'bcbEntityIdentifier',
        'bcbReferenceDate',
        'sisbacenCode',
        'cnpj',
        'ispb',
        'compeCode',
        'legalName',
        'reducedName',
        'tradeName',
        'acronym',
        'bcbStatusCode',
        'bcbStatusName',
        'institutionTypeCode',
        'institutionTypeName',
        'activeForSelection',
        'lastSynchronizedAt',
    ];

    protected function casts(): array
    {
        return [
            'bcbReferenceDate' => 'date',
            'activeForSelection' => 'boolean',
            'lastSynchronizedAt' => 'datetime',
        ];
    }
}
