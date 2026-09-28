<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_permission_implication', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('idPermission');
            $table->unsignedBigInteger('idImpliedPermission');
            $table->primary(['idPermission', 'idImpliedPermission'], 'pk_auth_permission_implication');
            $table->index('idImpliedPermission', 'idx_auth_permission_implication_implied');
            $table->foreign('idPermission', 'fk_auth_permission_implication_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idImpliedPermission', 'fk_auth_permission_implication_implied_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_permission_implication');
    }
};
