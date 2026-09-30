<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the global replay store for authenticated idempotent API mutations.
     */
    public function up(): void
    {
        Schema::create('apiIdempotencyRecord', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idUser');
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('tenantScopeKey', 32);
            $table->string('operation', 160);
            $table->uuid('idempotencyKey');
            $table->char('requestFingerprint', 64);
            $table->enum('state', ['PENDING', 'COMPLETED']);
            $table->unsignedSmallInteger('responseStatus')->nullable();
            $table->string('responseContentType', 128)->nullable();
            $table->mediumText('responseBody')->nullable();
            $table->dateTime('expiresAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idUser', 'tenantScopeKey', 'operation', 'idempotencyKey'], 'uk_api_idempotency_scope_operation_key');
            $table->index('expiresAt', 'idx_api_idempotency_expires_at');
            $table->foreign('idUser', 'fk_api_idempotency_user')
                ->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_api_idempotency_tenant')
                ->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Drop the global replay store for authenticated idempotent API mutations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apiIdempotencyRecord');
    }
};
