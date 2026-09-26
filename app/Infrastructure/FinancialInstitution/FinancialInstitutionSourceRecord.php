<?php

namespace App\Infrastructure\FinancialInstitution;

use Carbon\CarbonInterface;
use UnexpectedValueException;

final readonly class FinancialInstitutionSourceRecord
{
    public function __construct(
        public string $bcbEntityIdentifier,
        public CarbonInterface $bcbReferenceDate,
        public ?string $sisbacenCode,
        public ?string $cnpj,
        public string $legalName,
        public string $reducedName,
        public ?string $tradeName,
        public ?string $acronym,
        public string $bcbStatusCode,
        public string $bcbStatusName,
        public string $institutionTypeCode,
        public string $institutionTypeName,
    ) {}

    /**
     * Build a normalized record from an EntidadesSupervisionadas OData item.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromBcb(array $payload, CarbonInterface $referenceDate): self
    {
        return new self(
            bcbEntityIdentifier: self::required($payload, 'codigoIdentificadorBacen'),
            bcbReferenceDate: $referenceDate,
            sisbacenCode: self::optional($payload, 'codigoSisbacen'),
            cnpj: self::optional($payload, 'codigoCNPJ14'),
            legalName: self::required($payload, 'nomeEntidadeInteresse'),
            reducedName: self::required($payload, 'nomeReduzido'),
            tradeName: self::optional($payload, 'nomeFantasia'),
            acronym: self::optional($payload, 'siglaDaPessoaJuridica'),
            bcbStatusCode: self::required($payload, 'codigoTipoSituacaoPessoaJuridica'),
            bcbStatusName: self::required($payload, 'descricaoTipoSituacaoPessoaJuridica'),
            institutionTypeCode: self::required($payload, 'codigoTipoEntidadeSupervisionada'),
            institutionTypeName: self::required($payload, 'descricaoTipoEntidadeSupervisionada'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function required(array $payload, string $field): string
    {
        $value = self::optional($payload, $field);

        if ($value === null) {
            throw new UnexpectedValueException("BCB response is missing required field {$field}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function optional(array $payload, string $field): ?string
    {
        $value = $payload[$field] ?? null;

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
