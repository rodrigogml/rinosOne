<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the global BCB financial institution catalog.
     */
    public function up(): void
    {
        Schema::create('financialInstitution', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('bcbEntityIdentifier', 32);
            $table->date('bcbReferenceDate');
            $table->string('sisbacenCode', 32)->nullable();
            $table->char('cnpj', 14)->nullable();
            $table->char('ispb', 8)->nullable();
            $table->char('compeCode', 3)->nullable();
            $table->string('legalName', 255);
            $table->string('reducedName', 255);
            $table->string('tradeName', 255)->nullable();
            $table->string('acronym', 64)->nullable();
            $table->string('bcbStatusCode', 16);
            $table->string('bcbStatusName', 128);
            $table->string('institutionTypeCode', 16)->nullable();
            $table->string('institutionTypeName', 128)->nullable();
            $table->boolean('activeForSelection');
            $table->dateTime('lastSynchronizedAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();

            $table->unique('bcbEntityIdentifier', 'uk_financial_institution_bcb_entity_identifier');
            $table->index('cnpj', 'idx_financial_institution_cnpj');
            $table->index('ispb', 'idx_financial_institution_ispb');
            $table->index('compeCode', 'idx_financial_institution_compe_code');
            $table->index(['activeForSelection', 'reducedName'], 'idx_financial_institution_selection_name');
        });
    }

    /**
     * Drop the global BCB financial institution catalog.
     */
    public function down(): void
    {
        Schema::dropIfExists('financialInstitution');
    }
};
