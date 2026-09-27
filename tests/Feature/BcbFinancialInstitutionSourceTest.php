<?php

namespace Tests\Feature;

use App\Infrastructure\FinancialInstitution\BcbFinancialInstitutionSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BcbFinancialInstitutionSourceTest extends TestCase
{
    public function test_fetches_and_normalizes_all_odata_pages_for_a_reference_date(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['value' => [$this->payload()], '@odata.nextLink' => 'https://bcb.test/next'])
                ->push(['value' => [$this->payload('BCB-SECOND')]]),
        ]);
        config()->set('financial-institutions.bcb.base_url', 'https://bcb.test/odata');

        $records = app(BcbFinancialInstitutionSource::class)->fetch(CarbonImmutable::parse('2026-09-25'));

        $this->assertCount(2, $records);
        $this->assertSame('BCB-EXAMPLE', $records[0]->bcbEntityIdentifier);
        $this->assertSame('12AB34567890CD', $records[0]->cnpj);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "EntidadesSupervisionadas(dataBase='09-25-2026')"));
    }

    public function test_reports_a_bcb_http_failure_to_the_synchronization_service(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->expectException(RequestException::class);

        app(BcbFinancialInstitutionSource::class)->fetch(CarbonImmutable::parse('2026-09-25'));
    }

    public function test_accepts_an_official_record_without_an_institution_classification(): void
    {
        $payload = $this->payload();
        unset($payload['codigoTipoEntidadeSupervisionada'], $payload['descricaoTipoEntidadeSupervisionada']);
        Http::fake(['*' => Http::response(['value' => [$payload]])]);

        $record = app(BcbFinancialInstitutionSource::class)->fetch(CarbonImmutable::parse('2026-09-25'))[0];

        $this->assertNull($record->institutionTypeCode);
        $this->assertNull($record->institutionTypeName);
    }

    public function test_accepts_an_official_record_without_an_operating_status(): void
    {
        $payload = $this->payload();
        unset($payload['codigoTipoSituacaoPessoaJuridica'], $payload['descricaoTipoSituacaoPessoaJuridica']);
        Http::fake(['*' => Http::response(['value' => [$payload]])]);

        $record = app(BcbFinancialInstitutionSource::class)->fetch(CarbonImmutable::parse('2026-09-25'))[0];

        $this->assertNull($record->bcbStatusCode);
        $this->assertNull($record->bcbStatusName);
    }

    /** @return array<string, string> */
    private function payload(string $identifier = 'BCB-EXAMPLE'): array
    {
        return [
            'codigoIdentificadorBacen' => $identifier,
            'codigoSisbacen' => '12345',
            'codigoCNPJ14' => '12AB34567890CD',
            'nomeEntidadeInteresse' => 'Instituição Exemplo S.A.',
            'nomeReduzido' => 'Instituição exemplo',
            'nomeFantasia' => 'Exemplo',
            'siglaDaPessoaJuridica' => 'IESA',
            'codigoTipoSituacaoPessoaJuridica' => '3',
            'descricaoTipoSituacaoPessoaJuridica' => 'Autorizada em Atividade',
            'codigoTipoEntidadeSupervisionada' => '1',
            'descricaoTipoEntidadeSupervisionada' => 'Banco',
        ];
    }
}
