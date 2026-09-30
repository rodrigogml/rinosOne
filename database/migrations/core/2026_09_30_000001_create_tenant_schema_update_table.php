<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the operational lifecycle for updates of existing tenant schemas.
     */
    public function up(): void
    {
        Schema::create('tenantSchemaUpdate', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idTenant');
            $table->string('targetCatalog', 64);
            $table->enum('state', ['QUEUED', 'RUNNING', 'SUCCEEDED', 'FAILED']);
            $table->unsignedTinyInteger('attemptCount')->default(0);
            $table->string('lastFailureCode', 100)->nullable();
            $table->timestamp('startedAt')->nullable();
            $table->timestamp('completedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idTenant', 'targetCatalog'], 'uk_tenant_schema_update_target');
            $table->index(['state', 'updatedAt'], 'idx_tenant_schema_update_state_updated');
            $table->index(['state', 'completedAt'], 'idx_tenant_schema_update_completion');
            $table->foreign('idTenant', 'fk_tenant_schema_update_tenant')
                ->references('id')
                ->on('tenant')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Remove the tenant schema update lifecycle.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenantSchemaUpdate');
    }
};
