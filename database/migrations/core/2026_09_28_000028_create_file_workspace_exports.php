<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_workspaceExport', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('publicId', 26)->unique('uk_file_export_public_id');
            $table->unsignedBigInteger('idRequestingUser');
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('workspaceScope', 16);
            $table->json('selectionManifest');
            $table->string('state', 16);
            $table->string('displayName', 160);
            $table->string('storageKey', 255)->nullable();
            $table->unsignedBigInteger('storedSizeBytes')->nullable();
            $table->timestamp('expiresAt');
            $table->string('failureCode', 80)->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['state', 'expiresAt'], 'idx_file_export_state_expiry');
            $table->index('idRequestingUser', 'idx_file_export_requester');
            $table->index(['idTenant', 'workspaceScope'], 'idx_file_export_tenant_scope');
            $table->foreign('idRequestingUser', 'fk_file_export_requester')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_file_export_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_workspaceExport');
    }
};
