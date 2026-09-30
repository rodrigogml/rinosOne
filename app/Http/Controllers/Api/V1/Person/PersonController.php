<?php

namespace App\Http\Controllers\Api\V1\Person;

use App\Http\Controllers\Controller;
use App\Http\Requests\Person\CreatePersonRequest;
use App\Http\Requests\Person\DeletePersonRequest;
use App\Http\Requests\Person\DuplicatePersonRequest;
use App\Http\Requests\Person\InactivatePersonRequest;
use App\Http\Requests\Person\IndexPersonRequest;
use App\Http\Requests\Person\ReactivatePersonRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use App\Http\Resources\Person\PersonDetailResource;
use App\Http\Resources\Person\PersonSummaryResource;
use App\Services\Person\PersonAggregateService;
use App\Services\Person\PersonDeletionService;
use App\Services\Person\PersonDeletionUsageService;
use App\Services\Person\PersonDuplicationService;
use App\Services\Person\PersonLifecycleService;
use App\Services\Person\PersonQueryService;
use App\Services\Person\PersonRelationshipService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP boundary for the tenant-scoped People aggregate.
 *
 * Route registration and tenant authorization are deliberately kept outside
 * the domain services; each action uses the authorized connection placed in
 * the request by the route middleware.
 */
class PersonController extends Controller
{
    public function index(IndexPersonRequest $request, PersonQueryService $people): JsonResponse
    {
        $page = $people->paginate(
            $this->connection($request),
            $request->search(),
            $request->personType(),
            $request->status(),
            $request->pageNumber(),
            $request->perPage(),
        );

        return response()->json([
            'people' => $page->getCollection()->map(fn ($person) => (new PersonSummaryResource($person))->toArray($request))->values(),
            'pagination' => [
                'page' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function store(CreatePersonRequest $request, int $tenantId, PersonAggregateService $aggregate, PersonQueryService $people, PersonRelationshipService $relationships): JsonResponse
    {
        $connection = $this->connection($request);
        $person = $aggregate->create($connection, $request->aggregateData(), $request->user()->id, $request->header('Idempotency-Key'));
        $person = $people->detail($connection, $person->id);

        return response()->json(['person' => (new PersonDetailResource($person, $relationships->forPerson($connection, $person->id)))->toArray($request)], 201);
    }

    public function show(Request $request, int $tenantId, int $personId, PersonQueryService $people, PersonRelationshipService $relationships): JsonResponse
    {
        $connection = $this->connection($request);
        $person = $people->detail($connection, $personId);
        if ($person === null) {
            return response()->json(['error' => ['code' => 'PERSON_NOT_FOUND', 'message' => 'Pessoa não encontrada nesta organização.']], 404);
        }

        return response()->json(['person' => (new PersonDetailResource($person, $relationships->forPerson($connection, $personId)))->toArray($request)]);
    }

    public function update(UpdatePersonRequest $request, int $tenantId, int $personId, PersonAggregateService $aggregate, PersonQueryService $people, PersonRelationshipService $relationships): JsonResponse
    {
        $connection = $this->connection($request);
        $person = $aggregate->update($connection, $personId, $request->expectedVersion(), $request->aggregateData(), $request->user()->id, $request->header('Idempotency-Key'));
        $person = $people->detail($connection, $person->id);

        return response()->json(['person' => (new PersonDetailResource($person, $relationships->forPerson($connection, $person->id)))->toArray($request)]);
    }

    public function duplicate(DuplicatePersonRequest $request, int $tenantId, int $personId, PersonDuplicationService $duplication, PersonQueryService $people, PersonRelationshipService $relationships): JsonResponse
    {
        $connection = $this->connection($request);
        $person = $duplication->duplicate($connection, $personId, $request->options(), $request->expectedVersion(), $request->user()->id, $request->header('Idempotency-Key'));
        $person = $people->detail($connection, $person->id);

        return response()->json(['person' => (new PersonDetailResource($person, $relationships->forPerson($connection, $person->id)))->toArray($request)], 201);
    }

    public function inactivate(InactivatePersonRequest $request, int $tenantId, int $personId, PersonLifecycleService $lifecycle, PersonQueryService $people, PersonRelationshipService $relationships): JsonResponse
    {
        $connection = $this->connection($request);
        $lifecycle->inactivate($connection, $personId, $request->expectedVersion(), $request->user()->id, $request->header('Idempotency-Key'));
        $person = $people->detail($connection, $personId);

        return response()->json(['person' => (new PersonDetailResource($person, $relationships->forPerson($connection, $personId)))->toArray($request)]);
    }

    public function reactivate(ReactivatePersonRequest $request, int $tenantId, int $personId, PersonLifecycleService $lifecycle, PersonQueryService $people, PersonRelationshipService $relationships): JsonResponse
    {
        $connection = $this->connection($request);
        $lifecycle->reactivate($connection, $personId, $request->expectedVersion(), $request->user()->id, $request->header('Idempotency-Key'));
        $person = $people->detail($connection, $personId);

        return response()->json(['person' => (new PersonDetailResource($person, $relationships->forPerson($connection, $personId)))->toArray($request)]);
    }

    public function deletionUsage(Request $request, int $tenantId, int $personId, PersonQueryService $people, PersonDeletionUsageService $usage): JsonResponse
    {
        $connection = $this->connection($request);
        if ($people->detail($connection, $personId) === null) {
            return response()->json(['error' => ['code' => 'PERSON_NOT_FOUND', 'message' => 'Pessoa não encontrada nesta organização.']], 404);
        }

        return response()->json(['usages' => array_map(static fn ($item): array => ['module' => $item->module, 'description' => $item->description], $usage->inspect($connection, $personId))]);
    }

    public function destroy(DeletePersonRequest $request, int $tenantId, int $personId, PersonDeletionService $deletion): JsonResponse
    {
        $deletion->delete($this->connection($request), $personId, $request->expectedVersion(), $request->user()->id, $request->header('Idempotency-Key'));

        return response()->noContent();
    }

    private function connection(Request $request): ConnectionInterface
    {
        $connection = $request->attributes->get('person.tenant.connection');
        if (! $connection instanceof ConnectionInterface) {
            throw new \LogicException('The authorized Person tenant connection is missing.');
        }

        return $connection;
    }
}
