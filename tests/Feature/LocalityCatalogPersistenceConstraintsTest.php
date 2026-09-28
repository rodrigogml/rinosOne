<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalityCatalogPersistenceConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_locality_catalog_contains_the_required_tables_and_columns(): void
    {
        foreach ([
            'country',
            'brazilState',
            'brazilMunicipality',
            'postalCode',
            'localityReference',
            'postalCodeLocalityReference',
            'localityReferenceObservation',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertFalse(Schema::hasColumn('postalCodeLocalityReference', 'id'));
        $this->assertTrue(Schema::hasColumns('country', ['id', 'isoAlpha2', 'isoAlpha3', 'isoNumeric', 'activeForSelection']));
        $this->assertTrue(Schema::hasColumns('localityReference', ['idCountry', 'idBrazilState', 'idBrazilMunicipality', 'status', 'removalReason', 'removedAt']));
        $this->assertTrue(Schema::hasColumns('localityReferenceObservation', ['sourceKey', 'identitySignature', 'equivalenceSignature', 'observedPayload', 'firstSeenAt', 'lastSeenAt']));
    }

    public function test_country_codes_are_unique(): void
    {
        DB::table('country')->insert($this->countryAttributes());

        $this->expectException(QueryException::class);

        DB::table('country')->insert($this->countryAttributes(['isoAlpha2' => 'BR', 'isoAlpha3' => 'BRA', 'isoNumeric' => '999']));
    }

    public function test_postal_code_identity_is_unique_within_its_country_only(): void
    {
        $brazilId = DB::table('country')->insertGetId($this->countryAttributes());
        $unitedStatesId = DB::table('country')->insertGetId($this->countryAttributes([
            'isoAlpha2' => 'US',
            'isoAlpha3' => 'USA',
            'isoNumeric' => '840',
            'name' => 'Estados Unidos',
        ]));

        DB::table('postalCode')->insert([
            'idCountry' => $brazilId,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);
        DB::table('postalCode')->insert([
            'idCountry' => $unitedStatesId,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);

        $this->expectException(QueryException::class);

        DB::table('postalCode')->insert([
            'idCountry' => $brazilId,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);
    }

    public function test_postal_code_reference_relationship_prevents_duplicates(): void
    {
        $countryId = DB::table('country')->insertGetId($this->countryAttributes());
        $postalCodeId = DB::table('postalCode')->insertGetId([
            'idCountry' => $countryId,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);
        $referenceId = DB::table('localityReference')->insertGetId([
            'idCountry' => $countryId,
            'localityKind' => 'STREET',
            'status' => 'ACTIVE',
        ]);

        DB::table('postalCodeLocalityReference')->insert([
            'idPostalCode' => $postalCodeId,
            'idLocalityReference' => $referenceId,
        ]);

        $this->expectException(QueryException::class);

        DB::table('postalCodeLocalityReference')->insert([
            'idPostalCode' => $postalCodeId,
            'idLocalityReference' => $referenceId,
        ]);
    }

    public function test_postal_code_reference_relationship_cascades_when_the_postal_code_is_deleted(): void
    {
        $countryId = DB::table('country')->insertGetId($this->countryAttributes());
        $postalCodeId = DB::table('postalCode')->insertGetId([
            'idCountry' => $countryId,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);
        $referenceId = DB::table('localityReference')->insertGetId([
            'idCountry' => $countryId,
            'localityKind' => 'STREET',
            'status' => 'ACTIVE',
        ]);
        DB::table('postalCodeLocalityReference')->insert([
            'idPostalCode' => $postalCodeId,
            'idLocalityReference' => $referenceId,
        ]);

        DB::table('postalCode')->where('id', $postalCodeId)->delete();

        $this->assertSame(0, DB::table('postalCodeLocalityReference')->count());
    }

    public function test_brazilian_optional_territorial_references_are_nullified_when_the_state_is_deleted(): void
    {
        $countryId = DB::table('country')->insertGetId($this->countryAttributes());
        $stateId = DB::table('brazilState')->insertGetId([
            'idCountry' => $countryId,
            'ibgeCode' => '35',
            'abbreviation' => 'SP',
            'name' => 'São Paulo',
            'activeForSelection' => true,
        ]);
        $municipalityId = DB::table('brazilMunicipality')->insertGetId([
            'idBrazilState' => $stateId,
            'ibgeCode' => '3550308',
            'name' => 'São Paulo',
            'activeForSelection' => true,
        ]);
        $referenceId = DB::table('localityReference')->insertGetId([
            'idCountry' => $countryId,
            'idBrazilState' => $stateId,
            'idBrazilMunicipality' => $municipalityId,
            'localityKind' => 'STREET',
            'status' => 'ACTIVE',
        ]);

        DB::table('brazilState')->where('id', $stateId)->delete();

        $reference = DB::table('localityReference')->where('id', $referenceId)->first();

        $this->assertNull($reference->idBrazilState);
        $this->assertNull($reference->idBrazilMunicipality);
    }

    public function test_postal_code_reference_relationship_rejects_missing_global_parents(): void
    {
        $this->expectException(QueryException::class);

        DB::table('postalCodeLocalityReference')->insert([
            'idPostalCode' => 999999,
            'idLocalityReference' => 999999,
        ]);
    }

    public function test_observation_identity_is_source_scoped_and_equivalence_signature_is_optional(): void
    {
        $countryId = DB::table('country')->insertGetId($this->countryAttributes());
        $referenceId = DB::table('localityReference')->insertGetId([
            'idCountry' => $countryId,
            'localityKind' => 'STREET',
            'status' => 'ACTIVE',
        ]);

        DB::table('localityReferenceObservation')->insert([
            'idLocalityReference' => $referenceId,
            'sourceKey' => 'viacep',
            'identitySignature' => str_repeat('a', 64),
            'equivalenceSignature' => null,
            'observedPayload' => json_encode(['streetName' => 'Praça da Sé'], JSON_THROW_ON_ERROR),
            'firstSeenAt' => now(),
            'lastSeenAt' => now(),
        ]);

        $this->assertNull(DB::table('localityReferenceObservation')->value('equivalenceSignature'));

        $this->expectException(QueryException::class);

        DB::table('localityReferenceObservation')->insert([
            'idLocalityReference' => $referenceId,
            'sourceKey' => 'viacep',
            'identitySignature' => str_repeat('a', 64),
            'equivalenceSignature' => str_repeat('b', 64),
            'observedPayload' => json_encode(['streetName' => 'Praça da Sé'], JSON_THROW_ON_ERROR),
            'firstSeenAt' => now(),
            'lastSeenAt' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string|bool>
     */
    private function countryAttributes(array $overrides = []): array
    {
        return array_merge([
            'isoAlpha2' => 'BR',
            'isoAlpha3' => 'BRA',
            'isoNumeric' => '076',
            'name' => 'Brasil',
            'activeForSelection' => true,
        ], $overrides);
    }
}
