<?php

namespace Tests\Feature;

use App\Infrastructure\Locality\IbgeTerritoryApiSource;
use App\Infrastructure\Locality\IbgeTerritoryCatalog;
use App\Infrastructure\Locality\IbgeTerritoryMunicipalityRecord;
use App\Infrastructure\Locality\IbgeTerritorySource;
use App\Infrastructure\Locality\IbgeTerritoryStateRecord;
use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Services\Locality\IbgeTerritorySynchronizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;
use UnexpectedValueException;

class IbgeTerritorySynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('localities.ibge.base_url', 'https://ibge.test/api/v1/localidades');
        config()->set('localities.ibge.timeout_seconds', 5);
    }

    public function test_http_source_reads_the_current_ibge_state_and_municipality_structure(): void
    {
        Http::fake([
            'https://ibge.test/api/v1/localidades/estados' => Http::response([$this->statePayload()], 200),
            'https://ibge.test/api/v1/localidades/municipios' => Http::response([$this->municipalityPayload()], 200),
        ]);

        $catalog = app(IbgeTerritoryApiSource::class)->fetch();

        $this->assertSame('35', $catalog->states[0]->ibgeCode);
        $this->assertSame('SP', $catalog->states[0]->abbreviation);
        $this->assertSame('3550308', $catalog->municipalities[0]->ibgeCode);
        $this->assertSame('35', $catalog->municipalities[0]->stateIbgeCode);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ibge.test/api/v1/localidades/estados');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ibge.test/api/v1/localidades/municipios');
    }

    public function test_http_source_rejects_an_invalid_ibge_collection(): void
    {
        Http::fake([
            'https://ibge.test/api/v1/localidades/estados' => Http::response(['invalid' => true], 200),
        ]);

        $this->expectException(UnexpectedValueException::class);

        app(IbgeTerritoryApiSource::class)->fetch();
    }

    public function test_creates_updates_and_repeats_ibge_catalog_without_changing_internal_ids(): void
    {
        $service = new IbgeTerritorySynchronizationService($this->source($this->catalog()));

        $firstResult = $service->synchronize();
        $countryId = Country::sole()->id;
        $stateId = BrazilState::sole()->id;
        $municipality = BrazilMunicipality::sole();
        $municipalityId = $municipality->id;

        $changedCatalog = $this->catalog(stateName: 'São Paulo atualizado', municipalityName: 'São Paulo atualizado');
        $secondResult = (new IbgeTerritorySynchronizationService($this->source($changedCatalog)))->synchronize();
        $thirdResult = (new IbgeTerritorySynchronizationService($this->source($changedCatalog)))->synchronize();

        $this->assertTrue($firstResult->succeeded);
        $this->assertSame(1, $firstResult->createdCountryCount);
        $this->assertSame(1, $firstResult->createdStateCount);
        $this->assertSame(1, $firstResult->createdMunicipalityCount);
        $this->assertSame($countryId, Country::sole()->id);
        $this->assertSame($stateId, BrazilState::sole()->id);
        $this->assertSame($municipalityId, BrazilMunicipality::sole()->id);
        $this->assertSame('São Paulo atualizado', BrazilState::sole()->name);
        $this->assertSame('São Paulo atualizado', BrazilMunicipality::sole()->name);
        $this->assertSame(1, $secondResult->updatedStateCount);
        $this->assertSame(1, $secondResult->updatedMunicipalityCount);
        $this->assertSame(0, $thirdResult->createdStateCount);
        $this->assertSame(1, Country::query()->count());
        $this->assertSame(1, BrazilState::query()->count());
        $this->assertSame(1, BrazilMunicipality::query()->count());
    }

    public function test_absence_from_catalog_does_not_remove_or_deactivate_existing_territory(): void
    {
        (new IbgeTerritorySynchronizationService($this->source($this->catalog())))->synchronize();

        $result = (new IbgeTerritorySynchronizationService($this->source(new IbgeTerritoryCatalog([], []))))->synchronize();

        $this->assertTrue($result->succeeded);
        $this->assertSame(1, BrazilState::query()->count());
        $this->assertSame(1, BrazilMunicipality::query()->count());
        $this->assertTrue(BrazilState::sole()->activeForSelection);
        $this->assertTrue(BrazilMunicipality::sole()->activeForSelection);
    }

    public function test_source_failure_preserves_catalog_and_logs_only_a_safe_failure_code(): void
    {
        (new IbgeTerritorySynchronizationService($this->source($this->catalog())))->synchronize();

        Log::spy();
        $result = (new IbgeTerritorySynchronizationService($this->failingSource()))->synchronize();

        $this->assertFalse($result->succeeded);
        $this->assertSame(RuntimeException::class, $result->failureCode);
        $this->assertSame(1, BrazilState::query()->count());
        $this->assertSame(1, BrazilMunicipality::query()->count());
        Log::shouldHaveReceived('warning')->once()->with(
            'locality.ibge-territory.synchronization.failed',
            \Mockery::on(fn (array $context): bool => $context === [
                'source' => 'ibge',
                'failureCode' => RuntimeException::class,
            ]),
        );
    }

    private function source(IbgeTerritoryCatalog $catalog): IbgeTerritorySource
    {
        return new class($catalog) implements IbgeTerritorySource
        {
            public function __construct(private readonly IbgeTerritoryCatalog $catalog) {}

            public function fetch(): IbgeTerritoryCatalog
            {
                return $this->catalog;
            }
        };
    }

    private function failingSource(): IbgeTerritorySource
    {
        return new class implements IbgeTerritorySource
        {
            public function fetch(): IbgeTerritoryCatalog
            {
                throw new RuntimeException('IBGE endpoint unavailable');
            }
        };
    }

    private function catalog(string $stateName = 'São Paulo', string $municipalityName = 'São Paulo'): IbgeTerritoryCatalog
    {
        return new IbgeTerritoryCatalog(
            states: [new IbgeTerritoryStateRecord('35', 'SP', $stateName)],
            municipalities: [new IbgeTerritoryMunicipalityRecord('3550308', '35', $municipalityName)],
        );
    }

    /** @return array<string, mixed> */
    private function statePayload(): array
    {
        return ['id' => 35, 'sigla' => 'SP', 'nome' => 'São Paulo'];
    }

    /** @return array<string, mixed> */
    private function municipalityPayload(): array
    {
        return [
            'id' => 3550308,
            'nome' => 'São Paulo',
            'microrregiao' => [
                'mesorregiao' => [
                    'UF' => ['id' => 35],
                ],
            ],
        ];
    }
}
