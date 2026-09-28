<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_delegation', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idDelegatorUser');
            $table->unsignedBigInteger('idRecipientUser');
            $table->unsignedBigInteger('idPermission');
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('scope', 16);
            $table->string('originType', 40);
            $table->unsignedBigInteger('originId');
            $table->json('limits')->nullable();
            $table->timestamp('startsAt');
            $table->timestamp('endsAt');
            $table->string('state', 16);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idRecipientUser', 'scope', 'idTenant', 'state', 'startsAt', 'endsAt'], 'idx_auth_delegation_recipient_active');
            $table->index(['idDelegatorUser', 'state'], 'idx_auth_delegation_delegator_active');
            $table->foreign('idDelegatorUser', 'fk_auth_delegation_delegator_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idRecipientUser', 'fk_auth_delegation_recipient_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idPermission', 'fk_auth_delegation_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_auth_delegation_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_delegation');
    }
};
