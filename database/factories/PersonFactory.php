<?php

namespace Database\Factories;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Person> */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'personType' => PersonType::PF,
            'name' => $name,
            'displayName' => $name,
            'alias' => null,
            'cpf' => null,
            'cnpj' => null,
            'rg' => null,
            'rgIssuer' => null,
            'pisNis' => null,
            'passportNumber' => null,
            'foreignDocumentNumber' => null,
            'birthDate' => null,
            'foundationDate' => null,
            'notes' => null,
            'status' => PersonStatus::ACTIVE,
            'version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => PersonStatus::INACTIVE]);
    }

    public function legalEntity(): static
    {
        return $this->state(function (): array {
            $name = $this->faker->company();

            return [
                'personType' => PersonType::PJ,
                'name' => $name,
                'displayName' => $name,
            ];
        });
    }
}
