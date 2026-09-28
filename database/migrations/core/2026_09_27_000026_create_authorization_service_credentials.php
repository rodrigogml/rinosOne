<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_service_credential', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idServiceIdentity');
            $table->string('displayName', 160);
            $table->string('publicId', 32)->unique('uk_auth_service_credential_public_id');
            $table->string('secretHash');
            $table->json('permissionKeys')->nullable();
            $table->string('state', 16);
            $table->timestamp('expiresAt')->nullable();
            $table->timestamp('lastUsedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idServiceIdentity', 'state'], 'idx_auth_service_credential_identity_state');
            $table->foreign('idServiceIdentity', 'fk_auth_service_credential_identity')->references('id')->on('auth_service_identity')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_service_credential');
    }
};
