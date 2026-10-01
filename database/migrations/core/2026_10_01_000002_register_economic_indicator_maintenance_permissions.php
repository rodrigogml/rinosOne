<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $administratorRoleId = $this->roleId('platform.administrator', 'Platform administrator', 'Administers platform-wide capabilities.', $now);
        $operatorRoleId = $this->roleId(
            'platform.maintenance.economic-indicators.operator',
            'Economic indicators maintenance operator',
            'Views and synchronizes the global economic indicators.',
            $now,
        );

        foreach ([
            ['key' => 'platform.maintenance.economic-indicators.read', 'displayName' => 'View economic indicators maintenance', 'description' => 'Views the economic indicators maintenance state, technical history, and administrative audit.'],
            ['key' => 'platform.maintenance.economic-indicators.synchronize', 'displayName' => 'Synchronize economic indicators', 'description' => 'Requests a manual synchronization of global economic indicators from official sources.'],
        ] as $permission) {
            $permissionId = $this->permissionId($permission, $now);

            foreach ([$administratorRoleId, $operatorRoleId] as $roleId) {
                DB::table('auth_role_permission')->insertOrIgnore(['idRole' => $roleId, 'idPermission' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('auth_permission')->whereIn('key', [
            'platform.maintenance.economic-indicators.read',
            'platform.maintenance.economic-indicators.synchronize',
        ])->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('auth_role_permission')->whereIn('idPermission', $permissionIds)->delete();
            DB::table('auth_permission')->whereIn('id', $permissionIds)->delete();
        }

        DB::table('auth_role')->where('key', 'platform.maintenance.economic-indicators.operator')->delete();
    }

    /** @param array{key: string, displayName: string, description: string} $permission */
    private function permissionId(array $permission, DateTimeInterface $now): int
    {
        $id = DB::table('auth_permission')->where('key', $permission['key'])->value('id');

        return (int) ($id ?? DB::table('auth_permission')->insertGetId([
            ...$permission,
            'scope' => 'PLATFORM',
            'systemManaged' => true,
            'active' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]));
    }

    private function roleId(string $key, string $displayName, string $description, DateTimeInterface $now): int
    {
        $id = DB::table('auth_role')->where('key', $key)->value('id');

        return (int) ($id ?? DB::table('auth_role')->insertGetId([
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
        ]));
    }
};
