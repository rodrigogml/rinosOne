<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckResourceAuthorizationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'checks' => ['required', 'array', 'min:1', 'max:100'],
            'checks.*.permissionKey' => ['required', 'string', 'max:120'],
            'checks.*.resource' => ['required', 'array'],
            'checks.*.resource.type' => ['required', 'string', 'max:120'],
            'checks.*.resource.id' => ['required', 'integer', 'min:1'],
            'checks.*.tenantId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Os dados informados não são válidos.',
            ],
            'fields' => $validator->errors(),
        ], 422));
    }
}
