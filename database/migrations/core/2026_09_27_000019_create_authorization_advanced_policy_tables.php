<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_policy', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('scope', 16);
            $table->string('key', 160);
            $table->string('contextFingerprint', 192);
            $table->string('type', 80);
            $table->unsignedBigInteger('version');
            $table->json('definition');
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['scope', 'contextFingerprint', 'key', 'version'], 'uk_auth_policy_context_key_version');
            $table->index(['scope', 'idTenant', 'active'], 'idx_auth_policy_context_active');
            $table->foreign('idTenant', 'fk_auth_policy_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('auth_policy_binding', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPolicy');
            $table->unsignedBigInteger('idPermission');
            $table->unsignedBigInteger('idRole')->nullable();
            $table->unsignedBigInteger('idResourceType')->nullable();
            $table->unsignedBigInteger('resourceId')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idPermission', 'active'], 'idx_auth_policy_binding_permission_active');
            $table->index(['idRole', 'active'], 'idx_auth_policy_binding_role_active');
            $table->index(['idResourceType', 'resourceId', 'active'], 'idx_auth_policy_binding_resource_active');
            $table->foreign('idPolicy', 'fk_auth_policy_binding_policy')->references('id')->on('auth_policy')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idPermission', 'fk_auth_policy_binding_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idRole', 'fk_auth_policy_binding_role')->references('id')->on('auth_role')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idResourceType', 'fk_auth_policy_binding_resource_type')->references('id')->on('auth_resource_type')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_policy_binding');
        Schema::dropIfExists('auth_policy');
    }
};
