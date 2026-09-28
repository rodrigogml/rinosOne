<?php

namespace App\Http\Controllers\Api\V1\Locality;

use App\Http\Controllers\Controller;
use App\Services\Locality\LocalityPostalCodeNormalizer;
use App\Services\Locality\LocalityReferenceQueryService;
use App\Services\Locality\PostalReferenceEnrichmentRequestService;
use App\Services\Locality\PostalReferenceRefreshStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PostalReferenceLookupController extends Controller
{
    public function lookup(Request $request, LocalityReferenceQueryService $query, PostalReferenceEnrichmentRequestService $refresh): JsonResponse
    {
        try {
            $countryCode = (new LocalityPostalCodeNormalizer)->normalizeCountryCode((string) $request->input('countryCode'));
            $postalCode = (string) $request->input('postalCode');
            $candidates = $query->findActiveByPostalCode($countryCode, $postalCode);
            $state = $refresh->request($countryCode, $postalCode);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => ['code' => 'LOCALITY_LOOKUP_VALIDATION_FAILED', 'message' => 'Dados de consulta inválidos.']], 422);
        }

        return response()->json(['postalCode' => ['countryCode' => $countryCode, 'displayValue' => $postalCode], 'candidates' => $this->candidates($candidates), 'refresh' => ['state' => $state->state, 'pollAfterMilliseconds' => $state->pollAfterMilliseconds]]);
    }

    public function status(Request $request, LocalityReferenceQueryService $query, PostalReferenceRefreshStateService $refresh): JsonResponse
    {
        try {
            $normalizer = new LocalityPostalCodeNormalizer;
            $countryCode = $normalizer->normalizeCountryCode((string) $request->query('countryCode'));
            $postalCode = (string) $request->query('postalCode');
            $candidates = $query->findActiveByPostalCode($countryCode, $postalCode);
            $state = $refresh->state($countryCode, $normalizer->normalizePostalCode($postalCode));
        } catch (InvalidArgumentException) {
            return response()->json(['error' => ['code' => 'LOCALITY_LOOKUP_VALIDATION_FAILED', 'message' => 'Dados de consulta inválidos.']], 422);
        }

        return response()->json(['candidates' => $this->candidates($candidates), 'refresh' => ['state' => $state->state, 'pollAfterMilliseconds' => $state->pollAfterMilliseconds]]);
    }

    private function candidates($candidates): array
    {
        return $candidates->map(fn ($reference) => ['id' => $reference->id, 'kind' => $reference->localityKind, 'streetType' => $reference->streetType, 'streetName' => $reference->streetName, 'neighborhoodName' => $reference->neighborhoodName, 'municipality' => $reference->brazilMunicipality === null ? null : ['id' => $reference->brazilMunicipality->ibgeCode, 'name' => $reference->brazilMunicipality->name, 'stateAbbreviation' => $reference->brazilMunicipality->brazilState?->abbreviation]])->all();
    }
}
