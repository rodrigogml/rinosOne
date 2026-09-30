<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Register the stable tenant-scoped People capabilities and grant them to
     * the existing system tenant administrator role.
     */
    public function up(): void
    {
        $now = now();
        $tenantAdministratorId = DB::table('auth_role')
            ->where('key', 'tenant.administrator')
            ->where('scope', 'TENANT')
            ->where('type', 'SYSTEM')
            ->value('id');

        foreach ([
            ['key' => 'tenant.people.read', 'displayName' => 'View people', 'description' => 'Views people registered in the active tenant.'],
            ['key' => 'tenant.people.create', 'displayName' => 'Create people', 'description' => 'Creates people in the active tenant.'],
            ['key' => 'tenant.people.update', 'displayName' => 'Update people', 'description' => 'Updates people in the active tenant.'],
            ['key' => 'tenant.people.duplicate', 'displayName' => 'Duplicate people', 'description' => 'Duplicates people in the active tenant.'],
            ['key' => 'tenant.people.inactivate', 'displayName' => 'Inactivate people', 'description' => 'Inactivates people in the active tenant.'],
            ['key' => 'tenant.people.reactivate', 'displayName' => 'Reactivate people', 'description' => 'Reactivates people in the active tenant.'],
            ['key' => 'tenant.people.delete', 'displayName' => 'Delete people', 'description' => 'Physically deletes people in the active tenant.'],
        ] as $permission) {
            $permissionId = DB::table('auth_permission')->where('key', $permission['key'])->value('id')
                ?? DB::table('auth_permission')->insertGetId([
                    ...$permission,
                    'scope' => 'TENANT',
                    'systemManaged' => true,
                    'active' => true,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);

            if ($tenantAdministratorId !== null) {
                DB::table('auth_role_permission')->insertOrIgnore([
                    'idRole' => $tenantAdministratorId,
                    'idPermission' => $permissionId,
                ]);
            }
        }
    }

    /**
     * Remove People permissions and their role grants during rollback.
     */
    public function down(): void
    {
        $permissionIds = DB::table('auth_permission')
            ->whereIn('key', [
                'tenant.people.read',
                'tenant.people.create',
                'tenant.people.update',
                'tenant.people.duplicate',
                'tenant.people.inactivate',
                'tenant.people.reactivate',
                'tenant.people.delete',
            ])
            ->pluck('id');

        DB::table('auth_role_permission')->whereIn('idPermission', $permissionIds)->delete();
        DB::table('auth_permission')->whereIn('id', $permissionIds)->delete();
    }
};
