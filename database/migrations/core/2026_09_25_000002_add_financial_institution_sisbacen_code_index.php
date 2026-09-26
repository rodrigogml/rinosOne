<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the exact-lookup index for the BCB Sisbacen code.
     */
    public function up(): void
    {
        Schema::table('financialInstitution', function (Blueprint $table) {
            $table->index('sisbacenCode', 'idx_financial_institution_sisbacen_code');
        });
    }

    /**
     * Remove the exact-lookup index for the BCB Sisbacen code.
     */
    public function down(): void
    {
        Schema::table('financialInstitution', function (Blueprint $table) {
            $table->dropIndex('idx_financial_institution_sisbacen_code');
        });
    }
};
