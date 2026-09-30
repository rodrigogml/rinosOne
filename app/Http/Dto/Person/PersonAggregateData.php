<?php

namespace App\Http\Dto\Person;

use App\Domain\Person\PersonIdentityInput;

/**
 * Normalized transport shape for create and full-update People requests.
 *
 * Collection arrays remain transport data until their dedicated domain
 * services validate and persist them inside the aggregate transaction.
 */
readonly class PersonAggregateData
{
    /**
     * @param  list<array<string, mixed>>|null  $addresses
     * @param  list<array<string, mixed>>|null  $contacts
     * @param  list<array<string, mixed>>|null  $bankAccounts
     * @param  list<array<string, mixed>>|null  $pixKeys
     * @param  list<array<string, mixed>>|null  $relationships
     */
    public function __construct(
        public PersonIdentityInput $identity,
        public ?array $addresses,
        public ?array $contacts,
        public ?array $bankAccounts,
        public ?array $pixKeys,
        public ?array $relationships,
    ) {}
}
