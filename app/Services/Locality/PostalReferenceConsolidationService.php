<?php

namespace App\Services\Locality;

use App\Infrastructure\Locality\PostalReferenceSourceRecord;
use App\Models\BrazilMunicipality;
use App\Models\Country;
use App\Models\LocalityReference;
use App\Models\LocalityReferenceObservation;
use App\Models\PostalCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PostalReferenceConsolidationService
{
    /** @param list<PostalReferenceSourceRecord> $records */
    public function consolidate(array $records): void
    {
        foreach ($records as $record) {
            DB::transaction(fn () => $this->consolidateRecord($record));
        }
    }

    private function consolidateRecord(PostalReferenceSourceRecord $record): void
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $country = Country::query()->where('isoAlpha2', $record->countryCode)->firstOrFail();
        $postalCode = PostalCode::query()->firstOrCreate(
            ['idCountry' => $country->id, 'normalizedValue' => $record->normalizedPostalCode],
            ['displayValue' => $record->normalizedPostalCode],
        );
        $observation = LocalityReferenceObservation::query()
            ->where('sourceKey', $record->sourceKey)
            ->where('identitySignature', $record->identitySignature)
            ->first();

        if ($observation !== null) {
            $observation->update([
                'externalIdentifier' => $record->externalIdentifier,
                'observedPayload' => $record->observedValues,
                'equivalenceSignature' => $this->equivalenceSignature($record),
                'lastSeenAt' => $now,
            ]);
            $observation->localityReference->postalCodes()->syncWithoutDetaching([
                $postalCode->id => ['createdAt' => $now],
            ]);

            return;
        }

        $municipality = $this->municipality($country, $record);
        $reference = LocalityReference::query()->create([
            'idCountry' => $country->id,
            'idBrazilState' => $municipality?->idBrazilState,
            'idBrazilMunicipality' => $municipality?->id,
            'localityKind' => $this->kind($record),
            'streetType' => null,
            'streetName' => $record->streetName,
            'neighborhoodName' => $record->neighborhoodName,
            'cityNameObserved' => $municipality === null ? $record->municipalityName : null,
            'stateCodeObserved' => $municipality === null ? $record->stateAbbreviation : null,
            'status' => 'ACTIVE',
        ]);
        $reference->postalCodes()->attach($postalCode->id, ['createdAt' => $now]);
        $equivalenceSignature = $this->equivalenceSignature($record);
        LocalityReferenceObservation::query()->create([
            'idLocalityReference' => $reference->id,
            'sourceKey' => $record->sourceKey,
            'externalIdentifier' => $record->externalIdentifier,
            'identitySignature' => $record->identitySignature,
            'equivalenceSignature' => $equivalenceSignature,
            'observedPayload' => $record->observedValues,
            'firstSeenAt' => $now,
            'lastSeenAt' => $now,
        ]);

        if ($equivalenceSignature !== null && $this->hasActiveEquivalent($reference->id, $equivalenceSignature)) {
            $reference->update([
                'status' => 'REMOVED',
                'removalReason' => 'DUPLICATE_AUTOMATIC',
                'removedAt' => $now,
            ]);
        }
    }

    private function municipality(Country $country, PostalReferenceSourceRecord $record): ?BrazilMunicipality
    {
        if ($country->isoAlpha2 !== 'BR' || $record->municipalityIbgeCode === null) {
            return null;
        }

        return BrazilMunicipality::query()->where('ibgeCode', $record->municipalityIbgeCode)->first();
    }

    private function kind(PostalReferenceSourceRecord $record): string
    {
        if ($record->streetName !== null) {
            return 'STREET';
        }

        if ($record->neighborhoodName !== null) {
            return 'NEIGHBORHOOD';
        }

        return $record->municipalityName !== null ? 'MUNICIPALITY' : 'UNSPECIFIED';
    }

    private function equivalenceSignature(PostalReferenceSourceRecord $record): ?string
    {
        if ($record->municipalityIbgeCode === null || $record->streetName === null || $record->neighborhoodName === null) {
            return null;
        }

        return hash('sha256', json_encode([
            'countryCode' => $record->countryCode,
            'normalizedPostalCode' => $record->normalizedPostalCode,
            'municipalityIbgeCode' => $record->municipalityIbgeCode,
            'streetName' => $this->normalizedText($record->streetName),
            'neighborhoodName' => $this->normalizedText($record->neighborhoodName),
        ], JSON_THROW_ON_ERROR));
    }

    private function normalizedText(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 'UTF-8');
    }

    private function hasActiveEquivalent(int $referenceId, string $equivalenceSignature): bool
    {
        return LocalityReferenceObservation::query()
            ->where('equivalenceSignature', $equivalenceSignature)
            ->where('idLocalityReference', '!=', $referenceId)
            ->whereHas('localityReference', fn ($query) => $query->where('status', 'ACTIVE'))
            ->exists();
    }
}
