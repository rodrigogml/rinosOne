<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the global tenant-control migrations.
     */
    public function up(): void
    {
        Schema::create('tenant', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->ulid('id')->primary('pk_tenant');
            $table->string('displayName', 120);
            $table->enum('state', ['PROVISIONING', 'ACTIVE', 'INACTIVE', 'FAILED']);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index('state', 'idx_tenant_state');
        });

        Schema::create('tenantMembership', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->ulid('id')->primary('pk_tenant_membership');
            $table->char('idTenant', 26);
            $table->char('idUser', 26);
            $table->enum('role', ['OWNER']);
            $table->enum('state', ['ACTIVE', 'INACTIVE']);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idTenant', 'idUser'], 'uk_tenant_membership_tenant_user');
            $table->index(['idUser', 'state'], 'idx_tenant_membership_user_state');
            $table->foreign('idTenant', 'fk_tenant_membership_tenant')
                ->references('id')
                ->on('tenant')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idUser', 'fk_tenant_membership_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('tenantProvisioning', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->ulid('id')->primary('pk_tenant_provisioning');
            $table->char('idTenant', 26);
            $table->char('idRequestedByUser', 26);
            $table->char('idempotencyKey', 26);
            $table->enum('state', ['QUEUED', 'RUNNING', 'SUCCEEDED', 'FAILED']);
            $table->unsignedTinyInteger('attemptCount')->default(0);
            $table->string('lastFailureCode', 100)->nullable();
            $table->timestamp('completedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique('idTenant', 'uk_tenant_provisioning_tenant');
            $table->unique(['idRequestedByUser', 'idempotencyKey'], 'uk_tenant_provisioning_request_intent');
            $table->index(['state', 'updatedAt'], 'idx_tenant_provisioning_state_updated');
            $table->foreign('idTenant', 'fk_tenant_provisioning_tenant')
                ->references('id')
                ->on('tenant')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idRequestedByUser', 'fk_tenant_provisioning_requested_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the global tenant-control migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenantProvisioning');
        Schema::dropIfExists('tenantMembership');
        Schema::dropIfExists('tenant');
    }
};
