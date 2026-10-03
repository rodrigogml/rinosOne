<?php

namespace App\Http\Controllers\Api\V1\CalendarOccasion;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarOccasion\CalendarOccasionRequest;
use App\Models\CalendarOccasion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** HTTP API for global calendar-occasion definitions. */
class CalendarOccasionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('perPage', 100), 1), 100);
        $query = CalendarOccasion::query()->orderBy('name')->orderBy('id');
        if (($name = trim((string) $request->query('name'))) !== '') {
            $query->where('name', 'like', "%{$name}%");
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'calendarOccasions' => $page->getCollection()->map(fn (CalendarOccasion $occasion): array => $this->representation($occasion))->values(),
            'pagination' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
        ]);
    }

    public function store(CalendarOccasionRequest $request): JsonResponse
    {
        $occasion = CalendarOccasion::query()->create($this->attributes($request->validated()));

        return response()->json(['calendarOccasion' => $this->representation($occasion)], 201);
    }

    public function show(int $calendarOccasionId): JsonResponse
    {
        $occasion = CalendarOccasion::query()->find($calendarOccasionId);
        if ($occasion === null) {
            return $this->notFound();
        }

        return response()->json(['calendarOccasion' => $this->representation($occasion)]);
    }

    public function update(CalendarOccasionRequest $request, int $calendarOccasionId): JsonResponse
    {
        $occasion = CalendarOccasion::query()->find($calendarOccasionId);
        if ($occasion === null) {
            return $this->notFound();
        }
        $occasion->fill($this->attributes($request->validated()));
        $occasion->save();

        return response()->json(['calendarOccasion' => $this->representation($occasion)]);
    }

    public function destroy(int $calendarOccasionId): Response
    {
        $occasion = CalendarOccasion::query()->find($calendarOccasionId);
        if ($occasion === null) {
            return $this->notFound();
        }
        $occasion->delete();

        return response()->noContent();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'occasionCategory' => $data['occasionCategory'],
            'territorialScope' => $data['territorialScope'],
            'idCountry' => $data['locality']['countryId'],
            'idBrazilState' => $data['locality']['brazilStateId'] ?? null,
            'idBrazilMunicipality' => $data['locality']['brazilMunicipalityId'] ?? null,
            'validFrom' => $data['validity']['from'] ?? null,
            'validTo' => $data['validity']['to'] ?? null,
            'recurrenceType' => $data['recurrence']['type'],
            'oneTimeDate' => $data['recurrence']['oneTimeDate'] ?? null,
            'fixedMonth' => $data['recurrence']['fixedMonth'] ?? null,
            'fixedDay' => $data['recurrence']['fixedDay'] ?? null,
            'weekMonth' => $data['recurrence']['weekMonth'] ?? null,
            'weekOrdinal' => $data['recurrence']['weekOrdinal'] ?? null,
            'weekDay' => $data['recurrence']['weekDay'] ?? null,
            'easterOffsetDays' => $data['recurrence']['easterOffsetDays'] ?? null,
            'idReplacesOptionalDayOff' => $data['replacesOptionalDayOffDefinitionId'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function representation(CalendarOccasion $occasion): array
    {
        return [
            'id' => $occasion->id,
            'name' => $occasion->name,
            'occasionCategory' => $occasion->occasionCategory,
            'territorialScope' => $occasion->territorialScope,
            'locality' => ['countryId' => $occasion->idCountry, 'brazilStateId' => $occasion->idBrazilState, 'brazilMunicipalityId' => $occasion->idBrazilMunicipality],
            'validity' => ['from' => $occasion->validFrom?->format('Y-m-d'), 'to' => $occasion->validTo?->format('Y-m-d')],
            'recurrence' => ['type' => $occasion->recurrenceType, 'oneTimeDate' => $occasion->oneTimeDate?->format('Y-m-d'), 'fixedMonth' => $occasion->fixedMonth, 'fixedDay' => $occasion->fixedDay, 'weekMonth' => $occasion->weekMonth, 'weekOrdinal' => $occasion->weekOrdinal, 'weekDay' => $occasion->weekDay, 'easterOffsetDays' => $occasion->easterOffsetDays],
            'replacesOptionalDayOffDefinitionId' => $occasion->idReplacesOptionalDayOff,
            'createdAt' => $occasion->createdAt?->toISOString(),
            'updatedAt' => $occasion->updatedAt?->toISOString(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'CALENDAR_OCCASION_NOT_FOUND', 'message' => 'Esta definição de feriado não existe mais. Volte à lista, atualize-a e tente novamente.']], 404);
    }
}
