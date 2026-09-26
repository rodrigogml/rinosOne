<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_workspaceFolder', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idParentFolder')->nullable();
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('displayName', 160);
            $table->string('state', 16);
            $table->timestamp('trashedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idUser', 'idParentFolder', 'state'], 'idx_file_folder_user_parent_state');
            $table->index(['idTenant', 'idParentFolder', 'state'], 'idx_file_folder_tenant_parent_state');
            $table->foreign('idParentFolder', 'fk_file_folder_parent')->references('id')->on('file_workspaceFolder')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('idUser', 'fk_file_folder_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_file_folder_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
        Schema::table('file_filePossession', function (Blueprint $table): void {
            $table->unsignedBigInteger('idWorkspaceFolder')->nullable()->after('idTenant');
            $table->index('idWorkspaceFolder', 'idx_file_possession_folder');
            $table->foreign('idWorkspaceFolder', 'fk_file_possession_folder')->references('id')->on('file_workspaceFolder')->cascadeOnUpdate()->nullOnDelete();
        });
        // Owner exclusivity is enforced by WorkspaceFolder for MySQL 9 compatibility.
    }

    public function down(): void
    {
        Schema::table('file_filePossession', function (Blueprint $table): void {
            $table->dropForeign('fk_file_possession_folder');
            $table->dropIndex('idx_file_possession_folder');
            $table->dropColumn('idWorkspaceFolder');
        });
        Schema::dropIfExists('file_workspaceFolder');
    }
};
