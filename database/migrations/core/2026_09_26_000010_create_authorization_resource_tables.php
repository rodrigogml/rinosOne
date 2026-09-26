<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_resource_type', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('key', 120)->unique('uk_auth_resource_type_key');
            $table->string('scope', 16);
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });
        Schema::create('auth_resource_relation', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idResourceType');
            $table->unsignedBigInteger('resourceId');
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idGroup')->nullable();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('relationKey', 120);
            $table->string('scope', 16);
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idResourceType', 'resourceId', 'scope', 'idTenant', 'active'], 'idx_auth_resource_relation_context');
            $table->index(['idUser', 'active'], 'idx_auth_resource_relation_user');
            $table->index(['idGroup', 'active'], 'idx_auth_resource_relation_group');
            $table->foreign('idResourceType', 'fk_auth_resource_relation_type')->references('id')->on('auth_resource_type')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idUser', 'fk_auth_resource_relation_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idGroup', 'fk_auth_resource_relation_group')->references('id')->on('auth_group')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_auth_resource_relation_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_resource_relation');
        Schema::dropIfExists('auth_resource_type');
    }
};
