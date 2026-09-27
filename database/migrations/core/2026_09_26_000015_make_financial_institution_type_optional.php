<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permit records for which the BCB does not publish an institution classification.
     */
    public function up(): void
    {
        Schema::table('financialInstitution', function (Blueprint $table) {
            $table->string('institutionTypeCode', 16)->nullable()->change();
            $table->string('institutionTypeName', 128)->nullable()->change();
        });
    }

    /**
     * Restore the original mandatory classification contract.
     */
    public function down(): void
    {
        Schema::table('financialInstitution', function (Blueprint $table) {
            $table->string('institutionTypeCode', 16)->nullable(false)->change();
            $table->string('institutionTypeName', 128)->nullable(false)->change();
        });
    }
};
