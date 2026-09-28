<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_access_request', function (Blueprint $table): void {
            $table->engine = 'InnoDB'; $table->id();
            $table->unsignedBigInteger('idRequesterUser'); $table->unsignedBigInteger('idRecipientUser');
            $table->unsignedBigInteger('idPermission'); $table->unsignedBigInteger('idTenant')->nullable();
            $table->unsignedBigInteger('idApproverUser')->nullable();
            $table->string('scope', 16); $table->timestamp('startsAt'); $table->timestamp('endsAt');
            $table->string('state', 16); $table->timestamp('decidedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent(); $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idRecipientUser', 'scope', 'idTenant', 'state'], 'idx_auth_access_request_recipient_state');
            $table->foreign('idRequesterUser', 'fk_auth_access_request_requester')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idRecipientUser', 'fk_auth_access_request_recipient')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idApproverUser', 'fk_auth_access_request_approver')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idPermission', 'fk_auth_access_request_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_auth_access_request_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('auth_access_request'); }
};
