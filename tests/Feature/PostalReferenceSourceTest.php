<?php

namespace Tests\Feature;

use App\Infrastructure\Locality\BrasilApiPostalReferenceSource;
use App\Infrastructure\Locality\PostalReferenceSourceBatchService;
use App\Infrastructure\Locality\ViaCepPostalReferenceSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostalReferenceSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('localities.postal.via_cep.base_url', 'https://viacep.test/ws');
        config()->set('localities.postal.brasil_api.base_url', 'https://brasilapi.test/api/cep/v2');
        config()->set('localities.postal.via_cep.enabled', true);
        config()->set('localities.postal.brasil_api.enabled', true);
    }

    public function test_via_cep_normalizes_its_public_payload_to_the_internal_contract(): void
    {
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => Http::response($this->viaCepPayload(), 200),
        ]);

        $record = app(ViaCepPostalReferenceSource::class)->fetch('BR', '01001000')[0];

        $this->assertSame('VIA_CEP', $record->sourceKey);
        $this->assertSame('Praça da Sé', $record->streetName);
        $this->assertSame('São Paulo', $record->municipalityName);
        $this->assertSame('SP', $record->stateAbbreviation);
        $this->assertSame('3550308', $record->municipalityIbgeCode);
        $this->assertNotSame('', $record->identitySignature);
    }

    public function test_brasil_api_normalizes_its_public_payload_to_the_internal_contract(): void
    {
        Http::fake([
            'https://brasilapi.test/api/cep/v2/01001000' => Http::response($this->brasilApiPayload(), 200),
        ]);

        $record = app(BrasilApiPostalReferenceSource::class)->fetch('BR', '01001000')[0];

        $this->assertSame('BRASIL_API', $record->sourceKey);
        $this->assertSame('Praça da Sé', $record->streetName);
        $this->assertSame('3550308', $record->municipalityIbgeCode);
        $this->assertSame('01001000', $record->normalizedPostalCode);
    }

    public function test_batch_requests_enabled_sources_without_using_response_order_as_a_winner(): void
    {
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => Http::response($this->viaCepPayload(), 200),
            'https://brasilapi.test/api/cep/v2/01001000' => Http::response($this->brasilApiPayload(), 200),
        ]);

        $result = app(PostalReferenceSourceBatchService::class)->fetch('BR', '01001000');

        $this->assertFalse($result->hasFailures());
        $this->assertSame(['VIA_CEP', 'BRASIL_API'], array_map(static fn ($result): string => $result->sourceKey, $result->sources));
        $this->assertSame(['VIA_CEP', 'BRASIL_API'], array_map(static fn ($record): string => $record->sourceKey, $result->records()));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://viacep.test/ws/01001000/json/');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://brasilapi.test/api/cep/v2/01001000');
    }

    public function test_empty_invalid_and_failed_sources_are_isolated_from_other_source_results(): void
    {
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => Http::response(['erro' => true], 200),
            'https://brasilapi.test/api/cep/v2/01001000' => Http::failedConnection(),
        ]);

        $result = app(PostalReferenceSourceBatchService::class)->fetch('BR', '01001000');

        $this->assertTrue($result->hasFailures());
        $this->assertSame([], $result->sources[0]->records);
        $this->assertTrue($result->sources[0]->succeeded);
        $this->assertFalse($result->sources[1]->succeeded);
        $this->assertSame([], $result->records());
    }

    public function test_slow_valid_source_response_does_not_discard_the_other_source_result(): void
    {
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => static function () {
                usleep(25_000);

                return Http::response([
                    'cep' => '01001-000',
                    'logradouro' => 'Praça da Sé',
                    'bairro' => 'Sé',
                    'localidade' => 'São Paulo',
                    'uf' => 'SP',
                    'ibge' => '3550308',
                ], 200);
            },
            'https://brasilapi.test/api/cep/v2/01001000' => Http::response($this->brasilApiPayload(), 200),
        ]);

        $result = app(PostalReferenceSourceBatchService::class)->fetch('BR', '01001000');

        $this->assertFalse($result->hasFailures());
        $this->assertCount(2, $result->records());
    }

    public function test_incomplete_response_marks_only_its_source_as_failed_and_disabled_source_is_not_called(): void
    {
        config()->set('localities.postal.brasil_api.enabled', false);
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => Http::response(['cep' => '01001-000', 'uf' => 'SP'], 200),
        ]);

        $result = app(PostalReferenceSourceBatchService::class)->fetch('BR', '01001000');

        $this->assertTrue($result->hasFailures());
        $this->assertCount(1, $result->sources);
        $this->assertSame('VIA_CEP', $result->sources[0]->sourceKey);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'brasilapi.test'));
    }

    public function test_non_brazilian_or_invalid_postal_codes_do_not_call_brazilian_sources(): void
    {
        Http::fake();

        $result = app(PostalReferenceSourceBatchService::class)->fetch('US', 'SW1A1AA');

        $this->assertSame([], $result->sources);
        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function viaCepPayload(): array
    {
        return [
            'cep' => '01001-000',
            'logradouro' => 'Praça da Sé',
            'bairro' => 'Sé',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'ibge' => '3550308',
        ];
    }

    /** @return array<string, mixed> */
    private function brasilApiPayload(): array
    {
        return [
            'cep' => '01001000',
            'street' => 'Praça da Sé',
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'ibge' => ['city' => '3550308', 'state' => '35'],
        ];
    }
}
