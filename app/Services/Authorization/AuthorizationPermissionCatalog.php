<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationRoleType;
use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationPermissionCatalog
{
    public function __construct(private readonly AuthorizationAuditLogger $audit) {}

    public function register(string $key, string $displayName, string $description, AuthorizationScope $scope, bool $systemManaged = true): AuthorizationPermission
    {
        return DB::transaction(function () use ($key, $displayName, $description, $scope, $systemManaged): AuthorizationPermission {
            $permission = AuthorizationPermission::query()->firstOrCreate(
                ['key' => $key],
                [
                    'displayName' => $displayName,
                    'description' => $description,
                    'scope' => $scope->value,
                    'systemManaged' => $systemManaged,
                    'active' => true,
                ],
            );

            if ($permission->scope !== $scope->value) {
                throw new LogicException('An authorization permission key cannot change scope.');
            }

            if ($permission->wasRecentlyCreated) {
                $this->audit->record(
                    'authorization.permission.registered',
                    'authorization.permission',
                    $permission->id,
                    after: ['key' => $permission->key, 'scope' => $permission->scope, 'active' => $permission->active],
                );
            }

            if ($scope === AuthorizationScope::Tenant) {
                $administrator = AuthorizationRole::query()
                    ->where('key', 'tenant.administrator')
                    ->where('scope', AuthorizationScope::Tenant->value)
                    ->where('type', AuthorizationRoleType::System->value)
                    ->firstOrFail();

                app(AuthorizationRolePermissionService::class)->grant($administrator, $permission);
            }

            return $permission;
        });
    }
}
