<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_separation_rule', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('scope', 16);
            $table->string('contextFingerprint', 192);
            $table->unsignedBigInteger('idPermission');
            $table->unsignedBigInteger('idIncompatiblePermission');
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['scope', 'contextFingerprint', 'idPermission', 'idIncompatiblePermission'], 'uk_auth_separation_rule_pair');
            $table->index(['scope', 'idTenant', 'active'], 'idx_auth_separation_rule_context_active');
            $table->foreign('idTenant', 'fk_auth_separation_rule_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idPermission', 'fk_auth_separation_rule_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idIncompatiblePermission', 'fk_auth_separation_rule_incompatible_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_separation_rule');
    }
};
