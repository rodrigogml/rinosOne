<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economicIndicatorSeries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('code', 64);
            $table->string('kind', 16);
            $table->string('name', 160);
            $table->string('sourceKey', 32);
            $table->string('sourceSeriesCode', 64);
            $table->string('periodicity', 16);
            $table->string('unit', 32);
            $table->date('firstReferenceDate')->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique('code', 'uk_economic_indicator_series_code');
            $table->unique(['sourceKey', 'sourceSeriesCode'], 'uk_economic_indicator_series_source');
            $table->index(['kind', 'active'], 'idx_economic_indicator_series_kind_active');
        });

        Schema::create('economicIndicatorObservation', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idEconomicIndicatorSeries');
            $table->date('referenceDate');
            $table->decimal('value', 24, 12);
            $table->string('sourceIdentity', 191);
            $table->unsignedInteger('revision')->default(1);
            $table->string('currentKey', 191)->nullable();
            $table->dateTime('publishedAt', 6)->nullable();
            $table->dateTime('capturedAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idEconomicIndicatorSeries', 'sourceIdentity'], 'uk_economic_indicator_observation_source');
            $table->unique('currentKey', 'uk_economic_indicator_observation_current');
            $table->index(['idEconomicIndicatorSeries', 'referenceDate', 'revision'], 'idx_economic_indicator_observation_reference');
            $table->foreign('idEconomicIndicatorSeries', 'fk_economic_indicator_observation_series')
                ->references('id')->on('economicIndicatorSeries')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('economicPtaxQuote', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idEconomicIndicatorSeries');
            $table->date('referenceDate');
            $table->dateTime('quotedAt', 6);
            $table->decimal('buyRate', 24, 12);
            $table->decimal('sellRate', 24, 12);
            $table->decimal('midRate', 24, 12);
            $table->string('sourceIdentity', 191);
            $table->unsignedInteger('revision')->default(1);
            $table->string('currentKey', 191)->nullable();
            $table->dateTime('capturedAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idEconomicIndicatorSeries', 'sourceIdentity'], 'uk_economic_ptax_quote_source');
            $table->unique('currentKey', 'uk_economic_ptax_quote_current');
            $table->index(['idEconomicIndicatorSeries', 'referenceDate', 'revision'], 'idx_economic_ptax_quote_reference');
            $table->foreign('idEconomicIndicatorSeries', 'fk_economic_ptax_quote_series')
                ->references('id')->on('economicIndicatorSeries')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economicPtaxQuote');
        Schema::dropIfExists('economicIndicatorObservation');
        Schema::dropIfExists('economicIndicatorSeries');
    }
};
