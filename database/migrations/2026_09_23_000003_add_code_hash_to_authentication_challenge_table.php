<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authenticationChallenge', function (Blueprint $table) {
            $table->string('codeHash')->nullable()->after('secretHash');
        });
    }

    public function down(): void
    {
        Schema::table('authenticationChallenge', function (Blueprint $table) {
            $table->dropColumn('codeHash');
        });
    }
};
