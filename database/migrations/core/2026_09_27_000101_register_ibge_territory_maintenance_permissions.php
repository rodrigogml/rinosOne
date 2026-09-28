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
        $readerRoleId = $this->roleId(
            key: 'platform.maintenance.locality-ibge.reader',
            displayName: 'IBGE territory maintenance reader',
            description: 'Views the IBGE territory catalog maintenance state and technical history.',
            now: $now,
        );
        $permissionId = $this->permissionId($now);

        foreach ([$platformAdministratorRoleId, $readerRoleId] as $roleId) {
            DB::table('auth_role_permission')->insertOrIgnore([
                'idRole' => $roleId,
                'idPermission' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('auth_permission')
            ->where('key', 'platform.maintenance.locality-ibge.read')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('auth_role_permission')->where('idPermission', $permissionId)->delete();
            DB::table('auth_permission')->where('id', $permissionId)->delete();
        }

        DB::table('auth_role')->where('key', 'platform.maintenance.locality-ibge.reader')->delete();
    }

    private function permissionId(DateTimeInterface $now): int
    {
        $id = DB::table('auth_permission')
            ->where('key', 'platform.maintenance.locality-ibge.read')
            ->value('id');

        return $id ?? DB::table('auth_permission')->insertGetId([
            'key' => 'platform.maintenance.locality-ibge.read',
            'displayName' => 'View IBGE territory maintenance',
            'description' => 'Views the IBGE territory maintenance state and technical history.',
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
