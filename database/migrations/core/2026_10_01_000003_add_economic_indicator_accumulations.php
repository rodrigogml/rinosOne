<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('economicIndicatorSeries', function (Blueprint $table): void {
            $table->string('accumulationMode', 48)->default('COMPOUND_PUBLISHED_RATE')->after('unit');
        });

        Schema::table('economicIndicatorObservation', function (Blueprint $table): void {
            $table->decimal('accumulatedValue', 38, 18)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('economicIndicatorObservation', function (Blueprint $table): void {
            $table->dropColumn('accumulatedValue');
        });

        Schema::table('economicIndicatorSeries', function (Blueprint $table): void {
            $table->dropColumn('accumulationMode');
        });
    }
};
