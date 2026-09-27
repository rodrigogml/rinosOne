<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_policy_version', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('scope', 16);
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('subjectFingerprint', 128);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['scope', 'subjectFingerprint'], 'uk_auth_policy_version_context');
            $table->index(['scope', 'idTenant'], 'idx_auth_policy_version_context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_policy_version');
    }
};
