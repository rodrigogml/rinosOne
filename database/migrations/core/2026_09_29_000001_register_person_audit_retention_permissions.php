<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissionId = DB::table('auth_permission')->insertGetId([
            'key' => 'platform.maintenance.person-audit-retention.read',
            'displayName' => 'View Person audit retention',
            'description' => 'Views the Person audit retention state and technical history.',
            'scope' => 'PLATFORM',
            'systemManaged' => true,
            'active' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
        $administratorId = DB::table('auth_role')->where('key', 'platform.administrator')->value('id');
        if ($administratorId !== null) {
            DB::table('auth_role_permission')->insertOrIgnore([
                'idRole' => $administratorId,
                'idPermission' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('auth_permission')->where('key', 'platform.maintenance.person-audit-retention.read')->value('id');
        if ($permissionId !== null) {
            DB::table('auth_role_permission')->where('idPermission', $permissionId)->delete();
            DB::table('auth_permission')->where('id', $permissionId)->delete();
        }
    }
};
