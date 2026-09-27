<?php

namespace Tests\Feature;

use App\Infrastructure\FinancialInstitution\FinancialInstitutionSource;
use App\Infrastructure\FinancialInstitution\FinancialInstitutionSourceRecord;
use App\Models\FinancialInstitution;
use App\Services\FinancialInstitution\FinancialInstitutionSynchronizationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class FinancialInstitutionSynchronizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_and_updates_a_global_catalog_record_without_changing_its_internal_id(): void
    {
        $first = $this->record();
        $service = new FinancialInstitutionSynchronizationService($this->source([$first]));

        $firstResult = $service->synchronize(CarbonImmutable::parse('2026-09-25'));
        $institution = FinancialInstitution::sole();
        $id = $institution->id;

        $changed = $this->record(statusCode: '4', statusName: 'Paralisada', reducedName: 'Nome atualizado');
        $secondResult = (new FinancialInstitutionSynchronizationService($this->source([$changed])))
            ->synchronize(CarbonImmutable::parse('2026-09-26'));

        $institution->refresh();

        $this->assertTrue($firstResult->succeeded);
        $this->assertSame(1, $firstResult->createdCount);
        $this->assertTrue($secondResult->succeeded);
        $this->assertSame(1, $secondResult->updatedCount);
        $this->assertSame($id, $institution->id);
        $this->assertSame('Nome atualizado', $institution->reducedName);
        $this->assertFalse($institution->activeForSelection);
        $this->assertSame('2026-09-25', $institution->bcbReferenceDate->toDateString());
    }

    public function test_repeated_synchronization_is_idempotent_and_cnpj_is_not_unique(): void
    {
        $service = new FinancialInstitutionSynchronizationService($this->source([$this->record()]));

        $service->synchronize(CarbonImmutable::parse('2026-09-25'));
        $result = $service->synchronize(CarbonImmutable::parse('2026-09-25'));

        FinancialInstitution::query()->create([
            'bcbEntityIdentifier' => 'BCB-OTHER',
            'bcbReferenceDate' => '2026-09-25',
            'cnpj' => '12AB34567890CD',
            'legalName' => 'Outra instituição',
            'reducedName' => 'Outra',
            'bcbStatusCode' => '3',
            'bcbStatusName' => 'Autorizada em Atividade',
            'institutionTypeCode' => '1',
            'institutionTypeName' => 'Banco',
            'activeForSelection' => true,
            'lastSynchronizedAt' => CarbonImmutable::parse('2026-09-25'),
        ]);

        $this->assertTrue($result->succeeded);
        $this->assertSame(0, $result->createdCount);
        $this->assertSame(1, $result->updatedCount);
        $this->assertSame(2, FinancialInstitution::query()->count());
    }

    public function test_absence_from_a_response_does_not_remove_or_deactivate_a_catalog_record(): void
    {
        $service = new FinancialInstitutionSynchronizationService($this->source([$this->record()]));
        $service->synchronize(CarbonImmutable::parse('2026-09-25'));

        $result = (new FinancialInstitutionSynchronizationService($this->source([])))
            ->synchronize(CarbonImmutable::parse('2026-09-26'));

        $this->assertTrue($result->succeeded);
        $this->assertSame(1, FinancialInstitution::query()->count());
        $this->assertTrue(FinancialInstitution::sole()->activeForSelection);
    }

    public function test_persists_an_official_record_without_an_institution_classification(): void
    {
        $record = $this->record(institutionTypeCode: null, institutionTypeName: null);

        $result = (new FinancialInstitutionSynchronizationService($this->source([$record])))
            ->synchronize(CarbonImmutable::parse('2026-09-25'));

        $this->assertTrue($result->succeeded);
        $this->assertNull(FinancialInstitution::sole()->institutionTypeCode);
        $this->assertNull(FinancialInstitution::sole()->institutionTypeName);
    }

    public function test_persists_an_official_record_without_an_operating_status_as_not_selectable(): void
    {
        $record = $this->record(statusCode: null, statusName: null);

        $result = (new FinancialInstitutionSynchronizationService($this->source([$record])))
            ->synchronize(CarbonImmutable::parse('2026-09-25'));

        $institution = FinancialInstitution::sole();

        $this->assertTrue($result->succeeded);
        $this->assertNull($institution->bcbStatusCode);
        $this->assertNull($institution->bcbStatusName);
        $this->assertFalse($institution->activeForSelection);
    }

    public function test_source_failure_preserves_catalog_and_returns_a_safe_logged_result(): void
    {
        (new FinancialInstitutionSynchronizationService($this->source([$this->record()])))
            ->synchronize(CarbonImmutable::parse('2026-09-25'));

        Log::spy();
        $result = (new FinancialInstitutionSynchronizationService($this->failingSource()))
            ->synchronize(CarbonImmutable::parse('2026-09-26'));

        $this->assertFalse($result->succeeded);
        $this->assertSame(RuntimeException::class, $result->failureCode);
        $this->assertSame(1, FinancialInstitution::query()->count());
        Log::shouldHaveReceived('warning')->once()->with(
            'financial-institution.synchronization.failed',
            \Mockery::on(fn (array $context): bool => $context['source'] === 'bcb'),
        );
    }

    /**
     * @param  list<FinancialInstitutionSourceRecord>  $records
     */
    private function source(array $records): FinancialInstitutionSource
    {
        return new class($records) implements FinancialInstitutionSource
        {
            /** @param list<FinancialInstitutionSourceRecord> $records */
            public function __construct(private readonly array $records) {}

            public function fetch(CarbonInterface $referenceDate): array
            {
                return $this->records;
            }
        };
    }

    private function failingSource(): FinancialInstitutionSource
    {
        return new class implements FinancialInstitutionSource
        {
            public function fetch(CarbonInterface $referenceDate): array
            {
                throw new RuntimeException('BCB unavailable');
            }
        };
    }

    private function record(
        ?string $statusCode = '3',
        ?string $statusName = 'Autorizada em Atividade',
        string $reducedName = 'Instituição exemplo',
        ?string $institutionTypeCode = '1',
        ?string $institutionTypeName = 'Banco',
    ): FinancialInstitutionSourceRecord {
        return new FinancialInstitutionSourceRecord(
            bcbEntityIdentifier: 'BCB-EXAMPLE',
            bcbReferenceDate: CarbonImmutable::parse('2026-09-25'),
            sisbacenCode: '12345',
            cnpj: '12AB34567890CD',
            legalName: 'Instituição Exemplo S.A.',
            reducedName: $reducedName,
            tradeName: null,
            acronym: 'IESA',
            bcbStatusCode: $statusCode,
            bcbStatusName: $statusName,
            institutionTypeCode: $institutionTypeCode,
            institutionTypeName: $institutionTypeName,
        );
    }
}
