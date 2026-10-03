<?php

namespace Tests\Feature;

use Tests\TestCase;

class CalendarOccasionApiErrorContractTest extends TestCase
{
    public function test_invalid_definition_returns_clear_field_guidance(): void
    {
        $this->withoutMiddleware();

        $this->postJson('/api/v1/platform/calendar-occasions', [
            'name' => ' ',
            'occasionCategory' => 'HOLIDAY',
            'territorialScope' => 'COUNTRY',
            'locality' => ['countryId' => null],
            'recurrence' => ['type' => 'ANNUAL_FIXED_DATE'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CALENDAR_OCCASION_VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'Não foi possível salvar a definição porque há campos a corrigir. Revise as orientações abaixo de cada campo.')
            ->assertJsonPath('error.fields.name.0', 'Informe o nome que identificará este feriado.')
            ->assertJsonMissingPath('error.exception')
            ->assertJsonMissingPath('error.trace');
    }
}
