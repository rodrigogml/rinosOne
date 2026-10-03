<?php

namespace App\Http\Controllers\Api\V1\CalendarOccasion;

use App\Http\Controllers\Controller;
use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

/** Read-only location options for the calendar-occasion editor. */
class CalendarOccasionLocalityController extends Controller
{
    public function countries(): JsonResponse
    {
        return response()->json(['countries' => Country::query()->where('activeForSelection', true)->orderBy('name')->get(['id', 'name', 'isoAlpha2'])]);
    }

    public function states(int $countryId): JsonResponse
    {
        return response()->json(['states' => BrazilState::query()->where('idCountry', $countryId)->where('activeForSelection', true)->orderBy('name')->get(['id', 'name', 'abbreviation'])]);
    }

    public function municipalities(int $brazilStateId): JsonResponse
    {
        return response()->json(['municipalities' => BrazilMunicipality::query()->where('idBrazilState', $brazilStateId)->where('activeForSelection', true)->orderBy('name')->get(['id', 'name'])]);
    }
}
