<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_restriction', function (Blueprint $table): void {
            $table->unsignedBigInteger('idResourceType')->nullable()->after('idPermission');
            $table->unsignedBigInteger('resourceId')->nullable()->after('idResourceType');
            $table->index(['idResourceType', 'resourceId', 'active'], 'idx_auth_restriction_resource_active');
            $table->foreign('idResourceType', 'fk_auth_restriction_resource_type')->references('id')->on('auth_resource_type')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auth_restriction', function (Blueprint $table): void {
            $table->dropForeign('fk_auth_restriction_resource_type');
            $table->dropIndex('idx_auth_restriction_resource_active');
            $table->dropColumn(['idResourceType', 'resourceId']);
        });
    }
};
