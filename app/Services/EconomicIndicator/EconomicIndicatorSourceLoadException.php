<?php

namespace App\Services\EconomicIndicator;

use RuntimeException;
use Throwable;

/**
 * Identifies the source series and requested interval that prevented a synchronization.
 */
final class EconomicIndicatorSourceLoadException extends RuntimeException
{
    public function __construct(
        public readonly string $seriesCode,
        public readonly string $sourceKey,
        public readonly string $from,
        public readonly string $through,
        Throwable $previous,
    ) {
        parent::__construct('The economic indicator source could not be loaded.', previous: $previous);
    }
}
