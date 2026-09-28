<?php

namespace App\Infrastructure\Locality;

final readonly class IbgeTerritoryCatalog
{
    /**
     * @param  list<IbgeTerritoryStateRecord>  $states
     * @param  list<IbgeTerritoryMunicipalityRecord>  $municipalities
     */
    public function __construct(
        public array $states,
        public array $municipalities,
    ) {}
}
