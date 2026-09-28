<?php

namespace Tests\Feature;

use App\Infrastructure\Locality\PostalReferenceSourceBatchService;
use App\Jobs\Locality\PostalReferenceEnrichmentJob;
use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Services\Locality\PostalReferenceConsolidationService;
use App\Services\Locality\PostalReferenceEnrichmentRequestService;
use App\Services\Locality\PostalReferenceRefreshStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostalReferenceEnrichmentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('localities.postal.via_cep.base_url', 'https://viacep.test/ws');
        config()->set('localities.postal.brasil_api.base_url', 'https://brasilapi.test/api/cep/v2');
        $country = Country::query()->create(['isoAlpha2' => 'BR', 'isoAlpha3' => 'BRA', 'isoNumeric' => '076', 'name' => 'Brasil', 'activeForSelection' => true]);
        $state = BrazilState::query()->create(['idCountry' => $country->id, 'ibgeCode' => '35', 'abbreviation' => 'SP', 'name' => 'São Paulo', 'activeForSelection' => true]);
        BrazilMunicipality::query()->create(['idBrazilState' => $state->id, 'ibgeCode' => '3550308', 'name' => 'São Paulo', 'activeForSelection' => true]);
    }

    public function test_request_marks_pending_and_enqueues_one_after_commit_job_for_equivalent_requests(): void
    {
        Queue::fake();

        $first = app(PostalReferenceEnrichmentRequestService::class)->request(' br ', '01001-000');
        $second = app(PostalReferenceEnrichmentRequestService::class)->request('BR', '01001000');

        $this->assertSame(PostalReferenceRefreshStateService::PENDING, $first->state);
        $this->assertSame(1000, $first->pollAfterMilliseconds);
        $this->assertSame(PostalReferenceRefreshStateService::PENDING, $second->state);
        Queue::assertPushed(PostalReferenceEnrichmentJob::class, function (PostalReferenceEnrichmentJob $job): bool {
            return $job->countryCode === 'BR'
                && $job->normalizedPostalCode === '01001000'
                && $job->afterCommit === true;
        });
        Queue::assertPushed(PostalReferenceEnrichmentJob::class, 1);
    }

    public function test_job_completes_after_the_consumer_has_left_and_preserves_a_safe_completed_state(): void
    {
        $refreshStates = app(PostalReferenceRefreshStateService::class);
        $this->assertTrue($refreshStates->start('BR', '01001000'));
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => Http::response($this->viaCepPayload(), 200),
            'https://brasilapi.test/api/cep/v2/01001000' => Http::response($this->brasilApiPayload(), 200),
        ]);

        app(PostalReferenceEnrichmentJob::class, ['countryCode' => 'BR', 'normalizedPostalCode' => '01001000'])
            ->handle(app(PostalReferenceSourceBatchService::class), app(PostalReferenceConsolidationService::class), $refreshStates);

        $state = $refreshStates->state('BR', '01001000');
        $this->assertSame(PostalReferenceRefreshStateService::COMPLETED, $state->state);
        $this->assertNull($state->pollAfterMilliseconds);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://viacep.test/ws/01001000/json/');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://brasilapi.test/api/cep/v2/01001000');
    }

    public function test_partial_source_failure_completes_with_errors_without_losing_the_refresh_state(): void
    {
        $refreshStates = app(PostalReferenceRefreshStateService::class);
        $this->assertTrue($refreshStates->start('BR', '01001000'));
        Http::fake([
            'https://viacep.test/ws/01001000/json/' => Http::response($this->viaCepPayload(), 200),
            'https://brasilapi.test/api/cep/v2/01001000' => Http::failedConnection(),
        ]);

        (new PostalReferenceEnrichmentJob('BR', '01001000'))
            ->handle(app(PostalReferenceSourceBatchService::class), app(PostalReferenceConsolidationService::class), $refreshStates);

        $this->assertSame(PostalReferenceRefreshStateService::COMPLETED_WITH_ERRORS, $refreshStates->state('BR', '01001000')->state);
    }

    public function test_running_lock_refuses_concurrent_job_and_keeps_the_existing_pending_state(): void
    {
        $refreshStates = app(PostalReferenceRefreshStateService::class);
        $this->assertTrue($refreshStates->start('BR', '01001000'));
        $lock = Cache::lock($refreshStates->lockKey('BR', '01001000'), config('localities.postal.enrichment_lock_seconds'));
        $this->assertTrue($lock->get());
        Http::fake();

        try {
            (new PostalReferenceEnrichmentJob('BR', '01001000'))
                ->handle(app(PostalReferenceSourceBatchService::class), app(PostalReferenceConsolidationService::class), $refreshStates);
        } finally {
            $lock->release();
        }

        $this->assertSame(PostalReferenceRefreshStateService::PENDING, $refreshStates->state('BR', '01001000')->state);
        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function viaCepPayload(): array
    {
        return ['cep' => '01001-000', 'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'localidade' => 'São Paulo', 'uf' => 'SP', 'ibge' => '3550308'];
    }

    /** @return array<string, mixed> */
    private function brasilApiPayload(): array
    {
        return ['cep' => '01001000', 'street' => 'Praça da Sé', 'neighborhood' => 'Sé', 'city' => 'São Paulo', 'state' => 'SP', 'ibge' => ['city' => '3550308']];
    }
}
