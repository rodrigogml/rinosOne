<?php

namespace App\Infrastructure\FinancialInstitution;

use Carbon\CarbonInterface;

interface FinancialInstitutionSource
{
    /**
     * Return every BCB supervised entity published for the supplied reference date.
     *
     * @return list<FinancialInstitutionSourceRecord>
     */
    public function fetch(CarbonInterface $referenceDate): array;
}
