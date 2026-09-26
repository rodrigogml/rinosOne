<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_audit_event', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->dateTime('occurredAt', 6);
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('operation', 80);
            $table->string('targetType', 80);
            $table->unsignedBigInteger('targetId');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('correlationId', 100)->nullable();
            $table->index(['idTenant', 'occurredAt'], 'idx_auth_audit_tenant_occurred');
            $table->index(['targetType', 'targetId', 'occurredAt'], 'idx_auth_audit_target_occurred');
            $table->index('occurredAt', 'idx_auth_audit_occurred');
            $table->foreign('idActorUser', 'fk_auth_audit_actor_user')->references('id')->on('user')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('idTenant', 'fk_auth_audit_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit_event');
    }
};
