<?php

namespace Tests\Unit;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonIdentityInput;
use App\Domain\Person\PersonIdentityValidator;
use App\Domain\Person\PersonType;
use Tests\TestCase;

class PersonIdentityValidatorTest extends TestCase
{
    public function test_it_normalizes_and_validates_an_optional_cpf_for_a_physical_person(): void
    {
        $validated = (new PersonIdentityValidator)->validate(new PersonIdentityInput(PersonType::PF, ' Ada Lovelace ', ' Ada ', '529.982.247-25'));

        $this->assertSame('Ada Lovelace', $validated['name']);
        $this->assertSame('Ada', $validated['alias']);
        $this->assertSame('Ada Lovelace (Ada)', $validated['displayName']);
        $this->assertSame('52998224725', $validated['cpf']);
        $this->assertNull($validated['cnpj']);
    }

    public function test_it_accepts_people_without_cpf_or_cnpj_and_requires_only_type_compatible_documents(): void
    {
        $physical = (new PersonIdentityValidator)->validate(new PersonIdentityInput(PersonType::PF, 'Pessoa sem documento'));
        $legal = (new PersonIdentityValidator)->validate(new PersonIdentityInput(PersonType::PJ, 'Empresa sem documento'));

        $this->assertNull($physical['cpf']);
        $this->assertNull($legal['cnpj']);
    }

    public function test_it_rejects_invalid_or_incompatible_documents_and_dates(): void
    {
        try {
            (new PersonIdentityValidator)->validate(new PersonIdentityInput(
                PersonType::PJ,
                'Empresa',
                cpf: '52998224725',
                birthDate: '2020-01-01',
            ));
            $this->fail('Expected invalid identity input.');
        } catch (PersonValidationException $exception) {
            $this->assertSame('not_applicable', $exception->errors['cpf']);
            $this->assertSame('not_applicable', $exception->errors['birthDate']);
        }
    }

    public function test_it_validates_cnpj_and_rejects_repeated_digits(): void
    {
        $validated = (new PersonIdentityValidator)->validate(new PersonIdentityInput(PersonType::PJ, 'Empresa', cnpj: '04.252.011/0001-10'));
        $this->assertSame('04252011000110', $validated['cnpj']);

        $this->expectException(PersonValidationException::class);
        (new PersonIdentityValidator)->validate(new PersonIdentityInput(PersonType::PF, 'Pessoa', cpf: '111.111.111-11'));
    }
}
