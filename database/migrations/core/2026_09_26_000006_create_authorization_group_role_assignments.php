<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_group_role_assignment', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idRole');
            $table->unsignedBigInteger('idGroup');
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('state', 16);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idRole', 'idGroup'], 'uk_auth_group_role_assignment_role_group');
            $table->index(['idGroup', 'idTenant', 'state'], 'idx_auth_group_role_assignment_group_tenant_state');
            $table->foreign('idRole', 'fk_auth_group_role_assignment_role')->references('id')->on('auth_role')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idGroup', 'fk_auth_group_role_assignment_group')->references('id')->on('auth_group')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_auth_group_role_assignment_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_group_role_assignment');
    }
};
