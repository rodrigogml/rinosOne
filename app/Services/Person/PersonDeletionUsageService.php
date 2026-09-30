<?php

namespace App\Services\Person;

use App\Domain\Person\Deletion\PersonUsage;
use App\Domain\Person\Deletion\PersonUsageInspector;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use LogicException;

class PersonDeletionUsageService
{
    public const INSPECTOR_TAG = 'person.usage-inspectors';

    public function __construct(private readonly Container $container) {}

    /**
     * Inspects all usage providers registered by tenant modules. Providers are
     * deliberately discovered through a tag so the Person domain does not need
     * to know individual modules.
     *
     * @return list<PersonUsage>
     */
    public function inspect(ConnectionInterface $connection, int $personId): array
    {
        $usages = [];

        foreach ($this->container->tagged(self::INSPECTOR_TAG) as $inspector) {
            if (! $inspector instanceof PersonUsageInspector) {
                throw new LogicException(sprintf('A "%s" service must implement %s.', self::INSPECTOR_TAG, PersonUsageInspector::class));
            }

            foreach ($inspector->inspect($connection, $personId) as $usage) {
                if (! $usage instanceof PersonUsage) {
                    throw new LogicException(sprintf('%s must return only %s values.', $inspector::class, PersonUsage::class));
                }

                $usages[] = $usage;
            }
        }

        return $usages;
    }
}
