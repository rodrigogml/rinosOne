<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_workspaceTransfer', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('publicId', 26)->unique('uk_file_transfer_public_id');
            $table->unsignedBigInteger('idRequestingUser');
            $table->string('sourceScope', 16);
            $table->unsignedBigInteger('sourceTenantId')->nullable();
            $table->string('destinationScope', 16);
            $table->unsignedBigInteger('destinationTenantId')->nullable();
            $table->enum('mode', ['COPY', 'MOVE']);
            $table->json('selectionManifest');
            $table->enum('state', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED']);
            $table->unsignedInteger('totalItems');
            $table->unsignedInteger('processedItems')->default(0);
            $table->uuid('idempotencyKey')->nullable();
            $table->string('correlationId', 120)->nullable();
            $table->timestamp('leaseExpiresAt')->nullable();
            $table->timestamp('heartbeatAt')->nullable();
            $table->string('failureCode', 80)->nullable();
            $table->timestamp('startedAt')->nullable();
            $table->timestamp('completedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['state', 'leaseExpiresAt'], 'idx_file_transfer_state_lease');
            $table->index(['idRequestingUser', 'state'], 'idx_file_transfer_requester_state');
            $table->index(['sourceScope', 'sourceTenantId'], 'idx_file_transfer_source_context');
            $table->index(['destinationScope', 'destinationTenantId'], 'idx_file_transfer_destination_context');
            $table->foreign('idRequestingUser', 'fk_file_transfer_requester')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('sourceTenantId', 'fk_file_transfer_source_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('destinationTenantId', 'fk_file_transfer_destination_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('file_workspaceTransferReservation', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idWorkspaceTransfer');
            $table->enum('side', ['SOURCE', 'DESTINATION']);
            $table->string('workspaceScope', 16);
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->unsignedBigInteger('rootFolderId')->nullable();
            $table->timestamp('leaseExpiresAt');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['workspaceScope', 'idTenant', 'rootFolderId', 'leaseExpiresAt'], 'idx_file_transfer_reservation_context');
            $table->index(['idWorkspaceTransfer', 'side'], 'idx_file_transfer_reservation_transfer_side');
            $table->foreign('idWorkspaceTransfer', 'fk_file_transfer_reservation_transfer')->references('id')->on('file_workspaceTransfer')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_file_transfer_reservation_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('rootFolderId', 'fk_file_transfer_reservation_root_folder')->references('id')->on('file_workspaceFolder')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_workspaceTransferReservation');
        Schema::dropIfExists('file_workspaceTransfer');
    }
};
