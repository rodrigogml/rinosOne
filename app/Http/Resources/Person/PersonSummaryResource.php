<?php

namespace App\Http\Resources\Person;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Person */
class PersonSummaryResource extends JsonResource
{
    public static $wrap = null;

    /** @return array{id: int, personType: string, displayName: string, document: ?string, contactCount: int, status: string} */
    public function toArray(Request $request): array
    {
        /** @var Person $person */
        $person = $this->resource;

        return [
            'id' => $person->id,
            'personType' => $person->personType->value,
            'displayName' => $person->displayName,
            'document' => $person->cpf ?? $person->cnpj,
            'contactCount' => (int) ($person->contacts_count ?? 0),
            'status' => $person->status->value,
        ];
    }
}
