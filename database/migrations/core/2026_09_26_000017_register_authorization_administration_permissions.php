<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $platformAdministratorId = DB::table('auth_role')->where('key', 'platform.administrator')->value('id');

        foreach ([
            ['key' => 'tenant.authorization.read', 'displayName' => 'View tenant authorization', 'description' => 'Views access, explanations, and audit events within the tenant.', 'scope' => 'TENANT'],
            ['key' => 'tenant.authorization.manage', 'displayName' => 'Manage tenant authorization', 'description' => 'Manages tenant roles, groups, assignments, memberships, and restrictions.', 'scope' => 'TENANT'],
            ['key' => 'platform.authorization.tenant.read', 'displayName' => 'View tenant authorization from platform', 'description' => 'Views protected tenant authorization data as a platform administrator.', 'scope' => 'PLATFORM'],
            ['key' => 'platform.authorization.tenant.manage', 'displayName' => 'Manage tenant authorization from platform', 'description' => 'Manages protected tenant authorization data as a platform administrator.', 'scope' => 'PLATFORM'],
        ] as $permission) {
            $permissionId = DB::table('auth_permission')->where('key', $permission['key'])->value('id')
                ?? DB::table('auth_permission')->insertGetId([...$permission, 'systemManaged' => true, 'active' => true, 'createdAt' => $now, 'updatedAt' => $now]);

            if ($permission['scope'] === 'TENANT') {
                $tenantAdministratorId = DB::table('auth_role')->where('key', 'tenant.administrator')->value('id');
                DB::table('auth_role_permission')->insertOrIgnore(['idRole' => $tenantAdministratorId, 'idPermission' => $permissionId]);
            } elseif ($platformAdministratorId !== null) {
                DB::table('auth_role_permission')->insertOrIgnore(['idRole' => $platformAdministratorId, 'idPermission' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('auth_permission')->whereIn('key', ['tenant.authorization.read', 'tenant.authorization.manage', 'platform.authorization.tenant.read', 'platform.authorization.tenant.manage'])->pluck('id');
        DB::table('auth_role_permission')->whereIn('idPermission', $permissionIds)->delete();
        DB::table('auth_permission')->whereIn('id', $permissionIds)->delete();
    }
};
