<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_workspaceTransfer', function (Blueprint $table): void {
            $table->unsignedBigInteger('sourceUserId')->nullable()->after('sourceScope');
            $table->unsignedBigInteger('destinationUserId')->nullable()->after('destinationScope');
            $table->index(['sourceScope', 'sourceUserId'], 'idx_file_transfer_source_personal');
            $table->index(['destinationScope', 'destinationUserId'], 'idx_file_transfer_destination_personal');
            $table->foreign('sourceUserId', 'fk_file_transfer_source_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('destinationUserId', 'fk_file_transfer_destination_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('file_workspaceTransfer', function (Blueprint $table): void {
            $table->dropForeign('fk_file_transfer_source_user');
            $table->dropForeign('fk_file_transfer_destination_user');
            $table->dropIndex('idx_file_transfer_source_personal');
            $table->dropIndex('idx_file_transfer_destination_personal');
            $table->dropColumn(['sourceUserId', 'destinationUserId']);
        });
    }
};
