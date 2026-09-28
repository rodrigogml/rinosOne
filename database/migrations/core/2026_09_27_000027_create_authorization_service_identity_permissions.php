<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_service_identity_permission', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('idServiceIdentity');
            $table->unsignedBigInteger('idPermission');
            $table->primary(['idServiceIdentity', 'idPermission'], 'pk_auth_service_identity_permission');
            $table->foreign('idServiceIdentity', 'fk_auth_service_identity_permission_identity')->references('id')->on('auth_service_identity')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idPermission', 'fk_auth_service_identity_permission_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_service_identity_permission');
    }
};
