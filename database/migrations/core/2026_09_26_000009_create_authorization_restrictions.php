<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_restriction', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPermission');
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idGroup')->nullable();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('scope', 16);
            $table->timestamp('startsAt')->nullable();
            $table->timestamp('endsAt')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['scope', 'idTenant', 'idPermission', 'active'], 'idx_auth_restriction_context');
            $table->index(['idUser', 'active'], 'idx_auth_restriction_user');
            $table->index(['idGroup', 'active'], 'idx_auth_restriction_group');
            $table->foreign('idPermission', 'fk_auth_restriction_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idUser', 'fk_auth_restriction_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idGroup', 'fk_auth_restriction_group')->references('id')->on('auth_group')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_auth_restriction_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_restriction');
    }
};
