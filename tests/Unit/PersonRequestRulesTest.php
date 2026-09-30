<?php

namespace Tests\Unit;

use App\Http\Requests\Person\CreatePersonRequest;
use App\Http\Requests\Person\DuplicatePersonRequest;
use App\Http\Requests\Person\QuickCreatePersonRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PersonRequestRulesTest extends TestCase
{
    public function test_create_request_accepts_the_aggregate_transport_shape_without_principal_fields(): void
    {
        $validator = Validator::make([
            'personType' => 'PF',
            'name' => 'Ada Lovelace',
            'addresses' => [[
                'label' => 'Home',
                'addressType' => 'RESIDENTIAL',
                'idCountry' => 1,
                'street' => 'Rua Ada',
                'number' => '12A',
            ]],
            'contacts' => [['contactType' => 'EMAIL', 'value' => 'ada@example.test']],
            'bankAccounts' => [['label' => 'Account', 'accountType' => 'CHECKING']],
            'pixKeys' => [['keyType' => 'EMAIL', 'value' => 'ada@example.test']],
            'relationships' => [['idTargetPerson' => 2, 'relationshipType' => 'OTHER']],
        ], (new CreatePersonRequest)->rules());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_update_request_requires_version_and_rejects_unknown_collection_types(): void
    {
        $payload = ['personType' => 'PJ', 'name' => 'Rinos One Ltda.', 'addresses' => 'not-an-array'];

        $this->assertTrue(Validator::make($payload, (new UpdatePersonRequest)->rules())->fails());
        $payload['version'] = 2;
        $payload['addresses'] = [];
        $this->assertTrue(Validator::make($payload, (new UpdatePersonRequest)->rules())->passes());
    }

    public function test_quick_create_exposes_only_identity_rules_and_duplicate_requires_explicit_choices(): void
    {
        $this->assertTrue(Validator::make(['personType' => 'PF', 'name' => 'Ada'], (new QuickCreatePersonRequest)->rules())->passes());
        $this->assertTrue(Validator::make(['version' => 1, 'copyAddresses' => true], (new DuplicatePersonRequest)->rules())->fails());
        $this->assertTrue(Validator::make([
            'version' => 1,
            'copyAddresses' => false,
            'copyContacts' => false,
            'copyBankAccounts' => false,
            'copyPixKeys' => false,
        ], (new DuplicatePersonRequest)->rules())->passes());
    }
}
