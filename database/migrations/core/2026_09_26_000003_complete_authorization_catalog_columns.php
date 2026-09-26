<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_permission', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('displayName');
            $table->boolean('active')->default(true)->after('systemManaged');
        });
        Schema::table('auth_role', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('displayName');
            $table->boolean('active')->default(true)->after('systemManaged');
        });

        DB::table('auth_permission')->whereNull('description')->update(['description' => '']);
        DB::table('auth_role')->whereNull('description')->update(['description' => '']);

        Schema::table('auth_permission', fn (Blueprint $table) => $table->text('description')->nullable(false)->change());
        Schema::table('auth_role', fn (Blueprint $table) => $table->text('description')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('auth_role', fn (Blueprint $table) => $table->dropColumn(['description', 'active']));
        Schema::table('auth_permission', fn (Blueprint $table) => $table->dropColumn(['description', 'active']));
    }
};
