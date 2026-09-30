<?php

namespace Tests\Unit;

use App\Domain\Person\PersonRelationshipType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonRelationshipTypeTest extends TestCase
{
    #[DataProvider('relationshipPairs')]
    public function test_each_relationship_has_a_stable_inverse(PersonRelationshipType $relationship, PersonRelationshipType $inverse): void
    {
        $this->assertSame($inverse, $relationship->inverse());
        $this->assertSame($relationship, $inverse->inverse());
    }

    /** @return array<string, array{PersonRelationshipType, PersonRelationshipType}> */
    public static function relationshipPairs(): array
    {
        return [
            'child and parent' => [PersonRelationshipType::CHILD_OF, PersonRelationshipType::PARENT_OF],
            'grandchild and grandparent' => [PersonRelationshipType::GRANDCHILD_OF, PersonRelationshipType::GRANDPARENT_OF],
            'spouse' => [PersonRelationshipType::SPOUSE_OF, PersonRelationshipType::SPOUSE_OF],
            'partner' => [PersonRelationshipType::PARTNER_OF, PersonRelationshipType::PARTNER_OF],
            'employee and employer' => [PersonRelationshipType::EMPLOYEE_OF, PersonRelationshipType::EMPLOYER_OF],
            'contractor and contracting party' => [PersonRelationshipType::CONTRACTOR_OF, PersonRelationshipType::CONTRACTING_PARTY_OF],
            'other' => [PersonRelationshipType::OTHER, PersonRelationshipType::OTHER],
        ];
    }
}
