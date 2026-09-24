<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\Provisioning\ProvisioningFailureClassification;
use App\Domain\Tenant\Provisioning\TenantProvisioningFailure;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Throwable;

class TenantProvisioningFailureClassifier
{
    /** @var list<int> */
    private const TRANSIENT_DATABASE_ERROR_CODES = [1040, 1205, 1213, 2002, 2006, 2013];

    public function classify(Throwable $exception): TenantProvisioningFailure
    {
        if ($exception instanceof InvalidArgumentException || $exception instanceof LogicException) {
            return new TenantProvisioningFailure(
                ProvisioningFailureClassification::TERMINAL,
                'TENANT_PROVISIONING_CONFIGURATION_INVALID',
            );
        }

        if ($exception instanceof QueryException || $exception instanceof PDOException) {
            return $this->databaseFailure($exception);
        }

        return new TenantProvisioningFailure(
            ProvisioningFailureClassification::TERMINAL,
            'TENANT_PROVISIONING_FAILED',
        );
    }

    private function databaseFailure(QueryException|PDOException $exception): TenantProvisioningFailure
    {
        $code = $this->databaseErrorCode($exception);

        if ($code !== null && in_array($code, self::TRANSIENT_DATABASE_ERROR_CODES, true)) {
            return new TenantProvisioningFailure(
                ProvisioningFailureClassification::TRANSIENT,
                'TENANT_PROVISIONING_DATABASE_TRANSIENT',
            );
        }

        return new TenantProvisioningFailure(
            ProvisioningFailureClassification::TERMINAL,
            'TENANT_PROVISIONING_DATABASE_FAILED',
        );
    }

    private function databaseErrorCode(QueryException|PDOException $exception): ?int
    {
        $errorInfo = $exception instanceof QueryException ? $exception->errorInfo : $exception->errorInfo;

        if (is_array($errorInfo) && isset($errorInfo[1]) && is_numeric($errorInfo[1])) {
            return (int) $errorInfo[1];
        }

        return is_numeric($exception->getCode()) ? (int) $exception->getCode() : null;
    }
}
