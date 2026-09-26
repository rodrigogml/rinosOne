<?php

namespace App\Services\FinancialInstitution;

use App\Infrastructure\FinancialInstitution\FinancialInstitutionSource;
use App\Infrastructure\FinancialInstitution\FinancialInstitutionSourceRecord;
use App\Models\FinancialInstitution;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FinancialInstitutionSynchronizationService
{
    public function __construct(private readonly FinancialInstitutionSource $source) {}

    public function synchronize(?CarbonInterface $referenceDate = null): FinancialInstitutionSynchronizationResult
    {
        $startedAt = CarbonImmutable::now('America/Sao_Paulo');
        $referenceDate = CarbonImmutable::instance($referenceDate ?? $startedAt)->startOfDay();

        Log::info('financial-institution.synchronization.started', [
            'source' => 'bcb',
            'referenceDate' => $referenceDate->toDateString(),
        ]);

        try {
            $records = $this->source->fetch($referenceDate);
            [$createdCount, $updatedCount] = DB::transaction(function () use ($records, $startedAt): array {
                $createdCount = 0;
                $updatedCount = 0;

                foreach ($records as $record) {
                    $institution = FinancialInstitution::query()->firstOrNew([
                        'bcbEntityIdentifier' => $record->bcbEntityIdentifier,
                    ]);
                    $exists = $institution->exists;
                    $institution->fill($this->attributes($record, $startedAt));
                    $institution->save();

                    if ($exists) {
                        $updatedCount++;
                    } else {
                        $createdCount++;
                    }
                }

                return [$createdCount, $updatedCount];
            });
        } catch (Throwable $exception) {
            $result = new FinancialInstitutionSynchronizationResult(
                succeeded: false,
                startedAt: $startedAt,
                finishedAt: CarbonImmutable::now('America/Sao_Paulo'),
                referenceDate: $referenceDate->toDateString(),
                createdCount: 0,
                updatedCount: 0,
                failureCode: $exception::class,
            );

            Log::warning('financial-institution.synchronization.failed', [
                'source' => 'bcb',
                'referenceDate' => $result->referenceDate,
                'failureCode' => $result->failureCode,
            ]);

            return $result;
        }

        $result = new FinancialInstitutionSynchronizationResult(
            succeeded: true,
            startedAt: $startedAt,
            finishedAt: CarbonImmutable::now('America/Sao_Paulo'),
            referenceDate: $referenceDate->toDateString(),
            createdCount: $createdCount,
            updatedCount: $updatedCount,
        );

        Log::info('financial-institution.synchronization.completed', [
            'source' => 'bcb',
            'referenceDate' => $result->referenceDate,
            'createdCount' => $result->createdCount,
            'updatedCount' => $result->updatedCount,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(FinancialInstitutionSourceRecord $record, CarbonImmutable $synchronizedAt): array
    {
        return [
            'bcbReferenceDate' => $record->bcbReferenceDate->toDateString(),
            'sisbacenCode' => $record->sisbacenCode,
            'cnpj' => $record->cnpj === null ? null : strtoupper($record->cnpj),
            'legalName' => $record->legalName,
            'reducedName' => $record->reducedName,
            'tradeName' => $record->tradeName,
            'acronym' => $record->acronym,
            'bcbStatusCode' => $record->bcbStatusCode,
            'bcbStatusName' => $record->bcbStatusName,
            'institutionTypeCode' => $record->institutionTypeCode,
            'institutionTypeName' => $record->institutionTypeName,
            'activeForSelection' => $record->bcbStatusCode === '3',
            'lastSynchronizedAt' => $synchronizedAt,
        ];
    }
}
