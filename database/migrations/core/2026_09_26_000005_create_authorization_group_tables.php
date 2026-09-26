<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_group', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('displayName', 160);
            $table->string('scope', 16);
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['scope', 'idTenant', 'active'], 'idx_auth_group_scope_tenant_active');
            $table->foreign('idTenant', 'fk_auth_group_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->nullOnDelete();
        });

        Schema::create('auth_group_user', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('idGroup');
            $table->unsignedBigInteger('idUser');
            $table->primary(['idGroup', 'idUser'], 'pk_auth_group_user');
            $table->foreign('idGroup', 'fk_auth_group_user_group')->references('id')->on('auth_group')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idUser', 'fk_auth_group_user_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_group_user');
        Schema::dropIfExists('auth_group');
    }
};
