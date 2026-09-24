<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Record the latest successful contextual selection for each membership. */
    public function up(): void
    {
        Schema::table('tenantMembership', function (Blueprint $table) {
            $table->timestamp('lastContextSelectedAt')->nullable()->after('state');
            $table->index(['idUser', 'state', 'lastContextSelectedAt'], 'idx_tenant_membership_recent_context');
        });
    }

    /** Remove the persisted contextual-selection recency. */
    public function down(): void
    {
        Schema::table('tenantMembership', function (Blueprint $table) {
            $table->dropIndex('idx_tenant_membership_recent_context');
            $table->dropColumn('lastContextSelectedAt');
        });
    }
};
