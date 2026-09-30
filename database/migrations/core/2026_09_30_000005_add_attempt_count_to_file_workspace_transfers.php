<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_workspaceTransfer', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attemptCount')->default(0)->after('processedItems');
        });
    }

    public function down(): void
    {
        Schema::table('file_workspaceTransfer', function (Blueprint $table): void {
            $table->dropColumn('attemptCount');
        });
    }
};
