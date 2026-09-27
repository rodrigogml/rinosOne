<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['key' => 'personal.folder', 'scope' => 'PERSONAL'],
            ['key' => 'tenant.folder', 'scope' => 'TENANT'],
        ] as $type) {
            DB::table('auth_resource_type')->insertOrIgnore([
                ...$type,
                'active' => true,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        foreach ([
            ['key' => 'personal.folder.read', 'displayName' => 'Read personal workspace folder', 'scope' => 'PERSONAL'],
            ['key' => 'personal.folder.edit', 'displayName' => 'Edit personal workspace folder', 'scope' => 'PERSONAL'],
            ['key' => 'tenant.folder.read', 'displayName' => 'Read tenant workspace folder', 'scope' => 'TENANT'],
            ['key' => 'tenant.folder.edit', 'displayName' => 'Edit tenant workspace folder', 'scope' => 'TENANT'],
        ] as $permission) {
            DB::table('auth_permission')->insertOrIgnore([
                ...$permission,
                'description' => 'Requires an applicable workspace resource relation unless the workspace principal access applies.',
                'systemManaged' => true,
                'active' => true,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('auth_permission')->whereIn('key', [
            'personal.folder.read',
            'personal.folder.edit',
            'tenant.folder.read',
            'tenant.folder.edit',
        ])->delete();
        DB::table('auth_resource_type')->whereIn('key', ['personal.folder', 'tenant.folder'])->delete();
    }
};
