<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Establish the tenant migration baseline without introducing domain tables.
     */
    public function up(): void
    {
        // The migration repository is the only baseline structure in a new tenant schema.
    }

    /**
     * Reverse the tenant migration baseline.
     */
    public function down(): void
    {
        // No domain table exists in this baseline.
    }
};
