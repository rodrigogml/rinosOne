<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('idTenant')->nullable()->after('id');
            $table->index('idTenant', 'idx_auth_role_tenant');
            $table->foreign('idTenant', 'fk_auth_role_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auth_role', function (Blueprint $table): void {
            $table->dropForeign('fk_auth_role_tenant');
            $table->dropIndex('idx_auth_role_tenant');
            $table->dropColumn('idTenant');
        });
    }
};
