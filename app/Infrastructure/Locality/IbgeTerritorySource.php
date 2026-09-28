<?php

namespace App\Infrastructure\Locality;

interface IbgeTerritorySource
{
    /**
     * Return the current Brazilian territorial catalog published by IBGE.
     */
    public function fetch(): IbgeTerritoryCatalog;
}
