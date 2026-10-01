<?php

namespace Tests\Feature;

use App\Infrastructure\EconomicIndicator\BcbHttpRequestFactory;
use App\Infrastructure\EconomicIndicator\BcbPtaxSource;
use App\Infrastructure\EconomicIndicator\BcbSgsEconomicIndicatorSource;
use App\Models\EconomicIndicatorSeries;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class BcbEconomicIndicatorSourceTest extends TestCase
{
    public function test_native_certificate_authority_is_selected_without_disabling_tls_validation(): void
    {
        config()->set('economic-indicators.bcb.force_ipv4', false);
        config()->set('economic-indicators.bcb.ca_bundle', null);
        config()->set('economic-indicators.bcb.use_native_ca', true);

        $options = app(BcbHttpRequestFactory::class)->options();

        if (defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
            $this->assertSame([constant('CURLOPT_SSL_OPTIONS') => constant('CURLSSLOPT_NATIVE_CA')], $options['curl']);
            $this->assertArrayNotHasKey('verify', $options);

            return;
        }

        $this->assertSame([], $options);
    }

    public function test_explicit_certificate_bundle_takes_precedence_over_native_certificate_authority(): void
    {
        config()->set('economic-indicators.bcb.force_ipv4', false);
        config()->set('economic-indicators.bcb.ca_bundle', 'C:/certificates/platform-ca.pem');
        config()->set('economic-indicators.bcb.use_native_ca', true);

        $options = app(BcbHttpRequestFactory::class)->options();

        $this->assertSame('C:/certificates/platform-ca.pem', $options['verify']);
        $this->assertArrayNotHasKey('curl', $options);
    }

    public function test_normalizes_a_sgs_value_with_brazilian_decimal_separator(): void
    {
        Http::fake(['*' => Http::response([['data' => '01/10/2026', 'valor' => '0,1234']])]);
        config()->set('economic-indicators.bcb.sgs_base_url', 'https://bcb.test/sgs');

        $record = app(BcbSgsEconomicIndicatorSource::class)->fetch(
            $this->series('SELIC_DAILY', 'INDEX', '11'),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-01'),
        )[0];

        $this->assertSame('0.1234', $record->value);
        $this->assertSame('2026-10-01', $record->referenceDate->toDateString());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'bcdata.sgs.11/dados'));
    }

    public function test_selects_the_ptax_closing_bulletin_and_derives_mid_rate_without_float_math(): void
    {
        Http::fake(['*' => Http::response(['value' => [
            ['tipoBoletim' => 'Abertura', 'dataHoraCotacao' => '2026-10-01 10:00:00.000', 'cotacaoCompra' => 4.9, 'cotacaoVenda' => 5.0],
            ['tipoBoletim' => 'Fechamento', 'dataHoraCotacao' => '2026-10-01 13:00:00.000', 'cotacaoCompra' => 5.0, 'cotacaoVenda' => 5.1],
        ]])]);
        config()->set('economic-indicators.bcb.ptax_base_url', 'https://bcb.test/ptax');

        $records = app(BcbPtaxSource::class)->fetch(
            $this->series('PTAX_USD_BRL', 'PTAX', 'USD'),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-01'),
        );

        $this->assertCount(1, $records);
        $this->assertSame('5', $records[0]->buyRate);
        $this->assertSame('5.1', $records[0]->sellRate);
        $this->assertSame('5.050000000000', $records[0]->midRate);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'CotacaoMoedaPeriodo'));
    }

    public function test_rejects_an_invalid_sgs_record(): void
    {
        Http::fake(['*' => Http::response([['data' => '01/10/2026']])]);

        $this->expectException(UnexpectedValueException::class);

        app(BcbSgsEconomicIndicatorSource::class)->fetch(
            $this->series('SELIC_DAILY', 'INDEX', '11'),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-01'),
        );
    }

    public function test_returns_an_empty_result_when_sgs_reports_no_values_for_the_requested_period(): void
    {
        Http::fake(['*' => Http::response(['erro' => ['statusCode' => 404, 'detail' => 'Value(s) not found']])]);

        $records = app(BcbSgsEconomicIndicatorSource::class)->fetch(
            $this->series('IPCA_MONTHLY', 'INDEX', '433'),
            CarbonImmutable::parse('2026-09-20'),
            CarbonImmutable::parse('2026-10-01'),
        );

        $this->assertSame([], $records);
    }

    public function test_returns_an_empty_result_when_sgs_replies_with_http_not_found_for_the_requested_period(): void
    {
        Http::fake(['*' => Http::response(['erro' => ['statusCode' => 404, 'detail' => 'Value(s) not found']], 404)]);

        $records = app(BcbSgsEconomicIndicatorSource::class)->fetch(
            $this->series('IPCA_MONTHLY', 'INDEX', '433'),
            CarbonImmutable::parse('2026-09-20'),
            CarbonImmutable::parse('2026-10-01'),
        );

        $this->assertSame([], $records);
    }

    private function series(string $code, string $kind, string $sourceSeriesCode): EconomicIndicatorSeries
    {
        return new EconomicIndicatorSeries([
            'code' => $code,
            'kind' => $kind,
            'sourceKey' => $kind === 'PTAX' ? 'BCB_PTAX' : 'BCB_SGS',
            'sourceSeriesCode' => $sourceSeriesCode,
        ]);
    }
}
