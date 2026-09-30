<?php

namespace App\Domain\Person;

readonly class PersonDuplicationOptions
{
    public function __construct(
        public bool $copyAddresses = false,
        public bool $copyContacts = false,
        public bool $copyBankAccounts = false,
        public bool $copyPixKeys = false,
    ) {}
}
