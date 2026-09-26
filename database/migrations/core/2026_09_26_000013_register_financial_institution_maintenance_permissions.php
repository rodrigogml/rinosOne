<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $platformAdministratorRoleId = $this->roleId(
            key: 'platform.administrator',
            displayName: 'Platform administrator',
            description: 'Administers platform-wide capabilities.',
            now: $now,
        );
        $maintenanceOperatorRoleId = $this->roleId(
            key: 'platform.maintenance.financial-institution.operator',
            displayName: 'Financial institution maintenance operator',
            description: 'Views and synchronizes the financial institution catalog.',
            now: $now,
        );

        foreach ([
            [
                'key' => 'platform.maintenance.financial-institution.read',
                'displayName' => 'View financial institution maintenance',
                'description' => 'Views the financial institution maintenance state, technical history, and administrative audit.',
            ],
            [
                'key' => 'platform.maintenance.financial-institution.synchronize',
                'displayName' => 'Synchronize financial institutions',
                'description' => 'Requests a manual synchronization of the financial institution catalog from the official source.',
            ],
        ] as $permission) {
            $permissionId = $this->permissionId($permission, $now);

            foreach ([$platformAdministratorRoleId, $maintenanceOperatorRoleId] as $roleId) {
                DB::table('auth_role_permission')->insertOrIgnore([
                    'idRole' => $roleId,
                    'idPermission' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('auth_permission')
            ->whereIn('key', [
                'platform.maintenance.financial-institution.read',
                'platform.maintenance.financial-institution.synchronize',
            ])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('auth_role_permission')->whereIn('idPermission', $permissionIds)->delete();
            DB::table('auth_permission')->whereIn('id', $permissionIds)->delete();
        }

        DB::table('auth_role')->where('key', 'platform.maintenance.financial-institution.operator')->delete();
    }

    /**
     * @param  array{key: string, displayName: string, description: string}  $permission
     */
    private function permissionId(array $permission, DateTimeInterface $now): int
    {
        $id = DB::table('auth_permission')->where('key', $permission['key'])->value('id');

        return $id ?? DB::table('auth_permission')->insertGetId([
            ...$permission,
            'scope' => 'PLATFORM',
            'systemManaged' => true,
            'active' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    private function roleId(string $key, string $displayName, string $description, DateTimeInterface $now): int
    {
        $id = DB::table('auth_role')->where('key', $key)->value('id');

        return $id ?? DB::table('auth_role')->insertGetId([
            'idTenant' => null,
            'key' => $key,
            'displayName' => $displayName,
            'description' => $description,
            'scope' => 'PLATFORM',
            'type' => 'SYSTEM',
            'systemManaged' => true,
            'active' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }
};
