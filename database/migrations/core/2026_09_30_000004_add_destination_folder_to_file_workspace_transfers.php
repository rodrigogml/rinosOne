<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_workspaceTransfer', function (Blueprint $table): void {
            $table->unsignedBigInteger('destinationFolderId')->nullable()->after('destinationTenantId');
            $table->foreign('destinationFolderId', 'fk_file_transfer_destination_folder')->references('id')->on('file_workspaceFolder')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('file_workspaceTransfer', function (Blueprint $table): void {
            $table->dropForeign('fk_file_transfer_destination_folder');
            $table->dropColumn('destinationFolderId');
        });
    }
};
