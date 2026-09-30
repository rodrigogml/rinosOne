<?php

namespace Tests\Unit;

use App\Domain\Person\PersonRelationshipType;
use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Http\Resources\Person\PersonDetailResource;
use App\Http\Resources\Person\PersonSummaryResource;
use App\Models\Person;
use Illuminate\Http\Request;
use Tests\TestCase;

class PersonResourceTest extends TestCase
{
    public function test_summary_exposes_the_unmasked_document_and_no_internal_attributes(): void
    {
        $person = new Person([
            'personType' => PersonType::PF,
            'displayName' => 'Ada Lovelace',
            'cpf' => '52998224725',
            'status' => PersonStatus::ACTIVE,
            'version' => 4,
        ]);
        $person->id = 7;
        $person->setAttribute('contacts_count', 3);

        $data = (new PersonSummaryResource($person))->toArray(Request::create('/'));

        $this->assertSame('52998224725', $data['document']);
        $this->assertSame(3, $data['contactCount']);
        $this->assertArrayNotHasKey('version', $data);
    }

    public function test_detail_exposes_identity_without_serializing_internal_normalized_values(): void
    {
        $person = new Person([
            'personType' => PersonType::PJ,
            'name' => 'Rinos One Ltda.',
            'displayName' => 'Rinos One',
            'cnpj' => '12345678000195',
            'status' => PersonStatus::INACTIVE,
            'version' => 2,
        ]);
        $person->id = 8;

        $data = (new PersonDetailResource($person))->toArray(Request::create('/'));

        $this->assertSame('12345678000195', $data['cnpj']);
        $this->assertSame('INACTIVE', $data['status']);
        $this->assertSame(2, $data['version']);
        $this->assertArrayNotHasKey('normalizedValue', $data);
    }

    public function test_detail_projects_the_inverse_relationship_for_the_target_without_exposing_a_second_row(): void
    {
        $person = new Person(['personType' => PersonType::PF, 'name' => 'Ada', 'displayName' => 'Ada', 'status' => PersonStatus::ACTIVE, 'version' => 1]);
        $person->id = 8;

        $data = (new PersonDetailResource($person, [[
            'relationshipId' => 4,
            'direction' => 'INCOMING',
            'relationshipType' => PersonRelationshipType::PARENT_OF,
            'otherPersonId' => 9,
            'description' => null,
        ]]))->toArray(Request::create('/'));

        $this->assertSame(4, $data['relationships'][0]['id']);
        $this->assertSame('PARENT_OF', $data['relationships'][0]['relationshipType']);
        $this->assertSame('Pai/mãe de', $data['relationships'][0]['displayRelationshipType']);
    }
}
