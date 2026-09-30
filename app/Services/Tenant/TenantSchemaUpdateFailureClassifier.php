<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\SchemaUpdate\SchemaUpdateFailureClassification;
use App\Domain\Tenant\SchemaUpdate\TenantSchemaUpdateFailure;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Throwable;

class TenantSchemaUpdateFailureClassifier
{
    /** @var list<int> */
    private const TRANSIENT_DATABASE_ERROR_CODES = [1040, 1205, 1213, 2002, 2006, 2013];

    public function classify(Throwable $exception): TenantSchemaUpdateFailure
    {
        if ($exception instanceof InvalidArgumentException || $exception instanceof LogicException) {
            return new TenantSchemaUpdateFailure(
                SchemaUpdateFailureClassification::Terminal,
                'TENANT_SCHEMA_UPDATE_CATALOG_INVALID',
            );
        }

        if ($exception instanceof QueryException || $exception instanceof PDOException) {
            return $this->databaseFailure($exception);
        }

        return new TenantSchemaUpdateFailure(
            SchemaUpdateFailureClassification::Terminal,
            'TENANT_SCHEMA_UPDATE_FAILED',
        );
    }

    private function databaseFailure(QueryException|PDOException $exception): TenantSchemaUpdateFailure
    {
        $code = $this->databaseErrorCode($exception);

        if ($code !== null && in_array($code, self::TRANSIENT_DATABASE_ERROR_CODES, true)) {
            return new TenantSchemaUpdateFailure(
                SchemaUpdateFailureClassification::Transient,
                'TENANT_SCHEMA_UPDATE_DATABASE_TRANSIENT',
            );
        }

        return new TenantSchemaUpdateFailure(
            SchemaUpdateFailureClassification::Terminal,
            'TENANT_SCHEMA_UPDATE_DATABASE_FAILED',
        );
    }

    private function databaseErrorCode(QueryException|PDOException $exception): ?int
    {
        $errorInfo = $exception->errorInfo;

        if (is_array($errorInfo) && isset($errorInfo[1]) && is_numeric($errorInfo[1])) {
            return (int) $errorInfo[1];
        }

        return is_numeric($exception->getCode()) ? (int) $exception->getCode() : null;
    }
}
