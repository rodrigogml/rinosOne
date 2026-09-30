<?php

namespace App\Http\Controllers\Api\V1\Person;

use App\Http\Controllers\Controller;
use App\Services\Person\PersonReferenceCatalogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exposes active core catalog references to an authorized People tenant context. */
class PersonReferenceCatalogController extends Controller
{
    public function countries(PersonReferenceCatalogQuery $catalogs): JsonResponse { return response()->json(['countries' => $catalogs->countries()]); }
    public function states(int $countryId, PersonReferenceCatalogQuery $catalogs): JsonResponse { return response()->json(['states' => $catalogs->brazilStates($countryId)]); }
    public function municipalities(int $stateId, PersonReferenceCatalogQuery $catalogs): JsonResponse { return response()->json(['municipalities' => $catalogs->brazilMunicipalities($stateId)]); }
    public function financialInstitutions(Request $request, PersonReferenceCatalogQuery $catalogs): JsonResponse { return response()->json(['financialInstitutions' => $catalogs->financialInstitutions($request->query('search'))]); }
}
