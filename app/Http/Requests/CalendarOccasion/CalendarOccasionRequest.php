<?php

namespace App\Http\Requests\CalendarOccasion;

use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Validates a complete calendar-occasion definition and its territorial hierarchy. */
class CalendarOccasionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:160', 'not_regex:/^\s*$/u'],
            'occasionCategory' => ['required', 'string', 'in:HOLIDAY,OPTIONAL_DAY_OFF,COMMEMORATIVE_DATE'],
            'territorialScope' => ['required', 'string', 'in:COUNTRY,BRAZIL_STATE,BRAZIL_MUNICIPALITY'],
            'locality' => ['required', 'array'],
            'locality.countryId' => ['required', 'integer', 'min:1'],
            'locality.brazilStateId' => ['nullable', 'integer', 'min:1'],
            'locality.brazilMunicipalityId' => ['nullable', 'integer', 'min:1'],
            'validity' => ['nullable', 'array'],
            'validity.from' => ['nullable', 'date_format:Y-m-d'],
            'validity.to' => ['nullable', 'date_format:Y-m-d'],
            'recurrence' => ['required', 'array'],
            'recurrence.type' => ['required', 'string', 'in:ONE_TIME_DATE,ANNUAL_FIXED_DATE,ANNUAL_NTH_WEEKDAY,EASTER_OFFSET'],
            'recurrence.oneTimeDate' => ['nullable', 'date_format:Y-m-d'],
            'recurrence.fixedMonth' => ['nullable', 'integer', 'between:1,12'],
            'recurrence.fixedDay' => ['nullable', 'integer', 'between:1,31'],
            'recurrence.weekMonth' => ['nullable', 'integer', 'between:1,12'],
            'recurrence.weekOrdinal' => ['nullable', 'string', 'in:FIRST,SECOND,THIRD,FOURTH,FIFTH,LAST'],
            'recurrence.weekDay' => ['nullable', 'string', 'in:MONDAY,TUESDAY,WEDNESDAY,THURSDAY,FRIDAY,SATURDAY,SUNDAY'],
            'recurrence.easterOffsetDays' => ['nullable', 'integer', 'between:-366,366'],
            'replacesOptionalDayOffDefinitionId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Informe este campo para concluir o cadastro.',
            'string' => 'Informe um texto válido neste campo.',
            'integer' => 'Informe um número inteiro válido neste campo.',
            'array' => 'A estrutura informada para este grupo de campos não é válida.',
            'date_format' => 'Informe uma data no formato AAAA-MM-DD.',
            'in' => 'Selecione uma das opções disponíveis.',
            'between' => 'O valor informado está fora do intervalo permitido.',
            'min' => 'O valor informado é menor que o mínimo permitido.',
            'max' => 'O valor informado excede o limite permitido.',
            'name.required' => 'Informe o nome que identificará este feriado.',
            'name.not_regex' => 'Informe um nome com ao menos um caractere visível.',
            'locality.countryId.required' => 'Selecione o país ao qual este feriado pertence.',
            'recurrence.type.required' => 'Selecione como este feriado se repete.',
            'validity.from.date_format' => 'Informe a data de início no formato AAAA-MM-DD.',
            'validity.to.date_format' => 'Informe a data de fim no formato AAAA-MM-DD.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $this->validated();
            $locality = $data['locality'];
            $recurrence = $data['recurrence'];
            $scope = $data['territorialScope'];
            $category = $data['occasionCategory'];

            $country = Country::query()->find($locality['countryId']);
            if ($country === null) {
                $validator->errors()->add('locality.countryId', 'O país selecionado não existe. Escolha um país válido.');

                return;
            }

            $state = isset($locality['brazilStateId']) ? BrazilState::query()->find($locality['brazilStateId']) : null;
            $municipality = isset($locality['brazilMunicipalityId']) ? BrazilMunicipality::query()->find($locality['brazilMunicipalityId']) : null;
            if ($scope === 'COUNTRY' && ($state !== null || $municipality !== null)) {
                $validator->errors()->add('locality', 'A esfera País não pode ter estado ou município selecionados. Remova essas escolhas.');
            }
            if (in_array($scope, ['BRAZIL_STATE', 'BRAZIL_MUNICIPALITY'], true)) {
                if ($country->isoAlpha2 !== 'BR') {
                    $validator->errors()->add('locality.countryId', 'Feriados estaduais e municipais são cadastrados apenas para o Brasil. Selecione Brasil.');
                }
                if ($state === null) {
                    $validator->errors()->add('locality.brazilStateId', 'Selecione o estado brasileiro para esta esfera.');
                } elseif ($state->idCountry !== $country->id) {
                    $validator->errors()->add('locality.brazilStateId', 'O estado selecionado não pertence ao país informado. Selecione uma combinação compatível.');
                }
            }
            if ($scope === 'BRAZIL_STATE' && $municipality !== null) {
                $validator->errors()->add('locality.brazilMunicipalityId', 'A esfera estadual não pode ter município selecionado. Remova essa escolha.');
            }
            if ($scope === 'BRAZIL_MUNICIPALITY') {
                if ($municipality === null) {
                    $validator->errors()->add('locality.brazilMunicipalityId', 'Selecione o município brasileiro para esta esfera.');
                } elseif ($state === null || $municipality->idBrazilState !== $state->id) {
                    $validator->errors()->add('locality.brazilMunicipalityId', 'O município selecionado não pertence ao estado informado. Selecione uma combinação compatível.');
                }
            }
            if ($category === 'COMMEMORATIVE_DATE' && $scope !== 'COUNTRY') {
                $validator->errors()->add('territorialScope', 'Datas comemorativas são cadastradas somente na esfera País. Selecione País.');
            }
            if (isset($data['validity']['from'], $data['validity']['to']) && $data['validity']['to'] < $data['validity']['from']) {
                $validator->errors()->add('validity.to', 'A data de fim não pode ser anterior à data de início. Corrija o período de vigência.');
            }
            $this->validateRecurrence($validator, $recurrence);
        });
    }

    /** @param array<string, mixed> $recurrence */
    private function validateRecurrence(Validator $validator, array $recurrence): void
    {
        $type = $recurrence['type'];
        $labels = [
            'oneTimeDate' => 'a data da ocorrência',
            'fixedMonth' => 'o mês da data fixa anual',
            'fixedDay' => 'o dia da data fixa anual',
            'weekMonth' => 'o mês do dia ordinal',
            'weekOrdinal' => 'a ocorrência ordinal',
            'weekDay' => 'o dia da semana',
            'easterOffsetDays' => 'o deslocamento em dias da Páscoa',
        ];
        $required = match ($type) {
            'ONE_TIME_DATE' => ['oneTimeDate'],
            'ANNUAL_FIXED_DATE' => ['fixedMonth', 'fixedDay'],
            'ANNUAL_NTH_WEEKDAY' => ['weekMonth', 'weekOrdinal', 'weekDay'],
            'EASTER_OFFSET' => ['easterOffsetDays'],
        };
        $all = ['oneTimeDate', 'fixedMonth', 'fixedDay', 'weekMonth', 'weekOrdinal', 'weekDay', 'easterOffsetDays'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $recurrence) || $recurrence[$field] === null) {
                $validator->errors()->add("recurrence.$field", "Informe {$labels[$field]} para o tipo de recorrência selecionado.");
            }
        }
        foreach (array_diff($all, $required) as $field) {
            if (array_key_exists($field, $recurrence) && $recurrence[$field] !== null) {
                $validator->errors()->add("recurrence.$field", "Remova {$labels[$field]}: ele não é usado pelo tipo de recorrência selecionado.");
            }
        }
        if ($type === 'ANNUAL_FIXED_DATE' && isset($recurrence['fixedMonth'], $recurrence['fixedDay']) && ! checkdate((int) $recurrence['fixedMonth'], (int) $recurrence['fixedDay'], 2024)) {
            $validator->errors()->add('recurrence.fixedDay', 'O dia informado não existe no mês selecionado. Ajuste a data fixa anual.');
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['locality' => $this->nullify($this->input('locality', [])), 'validity' => $this->nullify($this->input('validity', [])), 'recurrence' => $this->nullify($this->input('recurrence', []))]);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function nullify(array $values): array
    {
        return array_map(static fn (mixed $value): mixed => $value === '' ? null : $value, $values);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['error' => ['code' => 'CALENDAR_OCCASION_VALIDATION_FAILED', 'message' => 'Não foi possível salvar a definição porque há campos a corrigir. Revise as orientações abaixo de cada campo.', 'fields' => $validator->errors()]], 422));
    }
}
