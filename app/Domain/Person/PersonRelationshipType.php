<?php

namespace App\Domain\Person;

enum PersonRelationshipType: string
{
    case CHILD_OF = 'CHILD_OF';
    case PARENT_OF = 'PARENT_OF';
    case GRANDCHILD_OF = 'GRANDCHILD_OF';
    case GRANDPARENT_OF = 'GRANDPARENT_OF';
    case SPOUSE_OF = 'SPOUSE_OF';
    case PARTNER_OF = 'PARTNER_OF';
    case EMPLOYEE_OF = 'EMPLOYEE_OF';
    case EMPLOYER_OF = 'EMPLOYER_OF';
    case CONTRACTOR_OF = 'CONTRACTOR_OF';
    case CONTRACTING_PARTY_OF = 'CONTRACTING_PARTY_OF';
    case OTHER = 'OTHER';

    /**
     * Returns the type shown when the same directed relation is viewed from
     * the target Person. OTHER intentionally remains semantically neutral.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::CHILD_OF => self::PARENT_OF,
            self::PARENT_OF => self::CHILD_OF,
            self::GRANDCHILD_OF => self::GRANDPARENT_OF,
            self::GRANDPARENT_OF => self::GRANDCHILD_OF,
            self::SPOUSE_OF => self::SPOUSE_OF,
            self::PARTNER_OF => self::PARTNER_OF,
            self::EMPLOYEE_OF => self::EMPLOYER_OF,
            self::EMPLOYER_OF => self::EMPLOYEE_OF,
            self::CONTRACTOR_OF => self::CONTRACTING_PARTY_OF,
            self::CONTRACTING_PARTY_OF => self::CONTRACTOR_OF,
            self::OTHER => self::OTHER,
        };
    }
}
