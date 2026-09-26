<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the global maintenance technical-history and administrative-audit tables.
     */
    public function up(): void
    {
        Schema::create('maintenanceExecutionHistory', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('routineKey', 100);
            $table->string('triggerType', 32);
            $table->string('state', 32);
            $table->dateTime('startedAt', 6);
            $table->dateTime('completedAt', 6)->nullable();
            $table->string('summary', 500)->nullable();
            $table->json('details')->nullable();
            $table->dateTime('expiresAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index(['routineKey', 'startedAt'], 'idx_maintenance_execution_routine_started');
            $table->index(['state', 'startedAt'], 'idx_maintenance_execution_state_started');
            $table->index('expiresAt', 'idx_maintenance_execution_expires');
        });

        Schema::create('maintenanceAdministrativeAudit', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPerformedByUser');
            $table->string('routineKey', 100);
            $table->string('action', 64);
            $table->string('outcome', 32);
            $table->json('parameters')->nullable();
            $table->dateTime('occurredAt', 6);
            $table->dateTime('expiresAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index(['idPerformedByUser', 'occurredAt'], 'idx_maintenance_audit_user_occurred');
            $table->index(['routineKey', 'occurredAt'], 'idx_maintenance_audit_routine_occurred');
            $table->index('expiresAt', 'idx_maintenance_audit_expires');
            $table->foreign('idPerformedByUser', 'fk_maintenance_audit_performed_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Drop the global maintenance technical-history and administrative-audit tables.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenanceAdministrativeAudit');
        Schema::dropIfExists('maintenanceExecutionHistory');
    }
};
