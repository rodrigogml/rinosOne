<?php

namespace App\Domain\Tenant;

final readonly class SchemaCompatibilityDecision
{
    private function __construct(
        public SchemaCompatibilityState $state,
    ) {}

    public static function compatible(): self
    {
        return new self(SchemaCompatibilityState::Compatible);
    }

    public static function incompatible(): self
    {
        return new self(SchemaCompatibilityState::Incompatible);
    }

    public function isCompatible(): bool
    {
        return $this->state === SchemaCompatibilityState::Compatible;
    }
}
