<?php

namespace App\Http\Requests\Drive;

use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDriveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'sourceTarget' => ['required', 'array'], 'sourceTarget.kind' => ['required', 'in:personal,tenant'], 'sourceTarget.tenantId' => ['nullable', 'integer', 'min:1'],
            'destinationTarget' => ['required', 'array'], 'destinationTarget.kind' => ['required', 'in:personal,tenant'], 'destinationTarget.tenantId' => ['nullable', 'integer', 'min:1'],
            'destinationTarget.folderId' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'], 'items.*.type' => ['required', 'in:folder,file'], 'items.*.id' => ['required', 'integer', 'min:1'],
            'mode' => ['required', 'in:COPY,MOVE'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['sourceTarget', 'destinationTarget'] as $key) {
                $target = $this->input($key, []);
                if (($target['kind'] ?? null) === 'tenant' && empty($target['tenantId'])) {
                    $validator->errors()->add("{$key}.tenantId", 'A organização é obrigatória para um workspace Work.');
                }
                if (($target['kind'] ?? null) === 'personal' && ! empty($target['tenantId'])) {
                    $validator->errors()->add("{$key}.tenantId", 'Um workspace pessoal não recebe organização.');
                }
            }
        });
    }

    /** @return list<array{type: string, id: int}> */
    public function items(): array
    {
        return array_map(fn (array $item): array => ['type' => $item['type'], 'id' => (int) $item['id']], $this->validated('items'));
    }

    /** @return array{kind: string, tenantId: ?int, folderId: ?int} */
    public function sourceTarget(): array
    {
        $target = $this->validated('sourceTarget');

        return ['kind' => $target['kind'], 'tenantId' => isset($target['tenantId']) ? (int) $target['tenantId'] : null, 'folderId' => null];
    }

    /** @return array{kind: string, tenantId: ?int, folderId: ?int} */
    public function destinationTarget(): array
    {
        $target = $this->validated('destinationTarget');

        return ['kind' => $target['kind'], 'tenantId' => isset($target['tenantId']) ? (int) $target['tenantId'] : null, 'folderId' => isset($target['folderId']) ? (int) $target['folderId'] : null];
    }

    public function mode(): WorkspaceTransferMode
    {
        return WorkspaceTransferMode::from($this->validated('mode'));
    }
}
