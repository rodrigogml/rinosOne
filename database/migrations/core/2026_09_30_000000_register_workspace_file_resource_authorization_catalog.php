<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['key' => 'personal.file', 'scope' => 'PERSONAL'],
            ['key' => 'tenant.file', 'scope' => 'TENANT'],
        ] as $type) {
            DB::table('auth_resource_type')->insertOrIgnore([
                ...$type,
                'active' => true,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        foreach ([
            ['key' => 'personal.file.read', 'displayName' => 'Read personal workspace file', 'scope' => 'PERSONAL'],
            ['key' => 'tenant.file.read', 'displayName' => 'Read tenant workspace file', 'scope' => 'TENANT'],
        ] as $permission) {
            DB::table('auth_permission')->insertOrIgnore([
                ...$permission,
                'description' => 'Requires an applicable direct workspace file relation unless workspace principal access applies.',
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
            'personal.file.read',
            'tenant.file.read',
        ])->delete();
        DB::table('auth_resource_type')->whereIn('key', [
            'personal.file',
            'tenant.file',
        ])->delete();
    }
};
