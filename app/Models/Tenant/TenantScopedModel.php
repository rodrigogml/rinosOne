<?php

namespace App\Models\Tenant;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;

abstract class TenantScopedModel extends Model
{
    /**
     * Bind this model instance to a connection resolved for one organization.
     *
     * The caller must resolve and authorize the tenant before invoking this method.
     */
    public function forTenantConnection(ConnectionInterface $connection): static
    {
        return $this->setConnection($connection->getName());
    }
}
