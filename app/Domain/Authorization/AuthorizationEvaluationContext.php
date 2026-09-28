<?php

namespace App\Domain\Authorization;

/** Server-derived attributes that may reduce an already eligible authorization decision. */
readonly class AuthorizationEvaluationContext
{
    /** @param list<int> $organizationUnitIds @param list<string> $logicalLocations */
    public function __construct(
        public ?float $amount = null,
        public array $organizationUnitIds = [],
        public ?int $utcMinute = null,
        public array $logicalLocations = [],
        public ?string $departmentCode = null,
        public ?string $employmentType = null,
    ) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->amount, $this->organizationUnitIds, $this->utcMinute, $this->logicalLocations, $this->departmentCode, $this->employmentType], JSON_THROW_ON_ERROR));
    }
}
