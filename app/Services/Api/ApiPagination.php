<?php

namespace App\Services\Api;

use Illuminate\Http\Request;

final readonly class ApiPagination
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $maximumPerPage = max(1, (int) config('api.pagination.maximumPerPage'));
        $defaultPerPage = min($maximumPerPage, max(1, (int) config('api.pagination.defaultPerPage')));

        return new self(
            max(1, $request->integer('page', 1)),
            min($maximumPerPage, max(1, $request->integer('perPage', $defaultPerPage))),
        );
    }
}
