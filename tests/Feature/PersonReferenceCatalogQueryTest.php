<?php

namespace Tests\Feature;

use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Models\FinancialInstitution;
use App\Services\Person\PersonReferenceCatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonReferenceCatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_only_active_country_territory_and_financial_catalog_entries(): void
    {
        $brazil = Country::query()->create(['isoAlpha2' => 'BR', 'isoAlpha3' => 'BRA', 'isoNumeric' => '076', 'name' => 'Brasil', 'activeForSelection' => true]);
        Country::query()->create(['isoAlpha2' => 'ZZ', 'isoAlpha3' => 'ZZZ', 'isoNumeric' => '999', 'name' => 'Inativo', 'activeForSelection' => false]);
        $state = BrazilState::query()->create(['idCountry' => $brazil->id, 'ibgeCode' => '35', 'abbreviation' => 'SP', 'name' => 'São Paulo', 'activeForSelection' => true]);
        BrazilMunicipality::query()->create(['idBrazilState' => $state->id, 'ibgeCode' => '3550308', 'name' => 'São Paulo', 'activeForSelection' => true]);
        FinancialInstitution::query()->create(['bcbEntityIdentifier' => '1', 'bcbReferenceDate' => '2026-01-01', 'legalName' => 'Banco de Teste', 'reducedName' => 'Banco Teste', 'activeForSelection' => true, 'lastSynchronizedAt' => now()]);
        FinancialInstitution::query()->create(['bcbEntityIdentifier' => '2', 'bcbReferenceDate' => '2026-01-01', 'legalName' => 'Banco Inativo', 'reducedName' => 'Banco Inativo', 'activeForSelection' => false, 'lastSynchronizedAt' => now()]);

        $catalog = app(PersonReferenceCatalogQuery::class);
        $this->assertSame([['id' => $brazil->id, 'isoAlpha2' => 'BR', 'name' => 'Brasil']], $catalog->countries());
        $this->assertSame([['id' => $state->id, 'abbreviation' => 'SP', 'name' => 'São Paulo']], $catalog->brazilStates($brazil->id));
        $this->assertSame('São Paulo', $catalog->brazilMunicipalities($state->id)[0]['name']);
        $this->assertSame('Banco Teste', $catalog->financialInstitutions('teste')[0]['name']);
    }
}
