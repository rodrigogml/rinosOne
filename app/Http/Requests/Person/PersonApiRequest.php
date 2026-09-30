<?php

namespace App\Http\Requests\Person;

use App\Http\Requests\Tenant\TenantRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class PersonApiRequest extends TenantRequest
{
    /**
     * Keep validation feedback local to the People contract instead of
     * leaking framework-default messages in the application's fallback
     * language. Field keys still allow the client to associate feedback with
     * the correct item in a collection.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O preenchimento deste campo é obrigatório.',
            'required_with' => 'O preenchimento deste campo é obrigatório.',
            'string' => 'O valor informado deve ser um texto.',
            'integer' => 'O valor informado deve ser numérico.',
            'boolean' => 'O valor informado deve ser verdadeiro ou falso.',
            'array' => 'A lista informada não é válida.',
            'date' => 'A data informada não é válida.',
            'in' => 'O valor informado não é permitido.',
            'min' => 'O valor informado é menor que o permitido.',
            'max' => 'O valor informado excede o limite permitido.',
            'not_regex' => 'O valor informado não é válido.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'PERSON_VALIDATION_FAILED',
                'message' => 'Os dados da Pessoa não são válidos.',
                'fields' => $validator->errors(),
            ],
        ], 400));
    }
}
