<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_workspaceFolder', function (Blueprint $table): void {
            $table->timestamp('purgeAfter')->nullable()->after('trashedAt');
            $table->index(['state', 'purgeAfter'], 'idx_file_folder_state_purge');
        });
    }

    public function down(): void
    {
        Schema::table('file_workspaceFolder', function (Blueprint $table): void {
            $table->dropIndex('idx_file_folder_state_purge');
            $table->dropColumn('purgeAfter');
        });
    }
};
