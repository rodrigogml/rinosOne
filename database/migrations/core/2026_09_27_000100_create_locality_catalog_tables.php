<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the global territorial and postal-reference catalogs.
     */
    public function up(): void
    {
        Schema::create('country', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->char('isoAlpha2', 2);
            $table->char('isoAlpha3', 3);
            $table->char('isoNumeric', 3);
            $table->string('name', 120);
            $table->boolean('activeForSelection');
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique('isoAlpha2', 'uk_country_iso_alpha2');
            $table->unique('isoAlpha3', 'uk_country_iso_alpha3');
            $table->unique('isoNumeric', 'uk_country_iso_numeric');
            $table->index('activeForSelection', 'idx_country_active_selection');
        });

        Schema::create('brazilState', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idCountry');
            $table->char('ibgeCode', 2);
            $table->char('abbreviation', 2);
            $table->string('name', 120);
            $table->boolean('activeForSelection');
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique('ibgeCode', 'uk_brazil_state_ibge_code');
            $table->unique('abbreviation', 'uk_brazil_state_abbreviation');
            $table->index('activeForSelection', 'idx_brazil_state_active_selection');
            $table->foreign('idCountry', 'fk_brazil_state_country')
                ->references('id')
                ->on('country')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('brazilMunicipality', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idBrazilState');
            $table->char('ibgeCode', 7);
            $table->string('name', 160);
            $table->boolean('activeForSelection');
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique('ibgeCode', 'uk_brazil_municipality_ibge_code');
            $table->index('activeForSelection', 'idx_brazil_municipality_active_selection');
            $table->foreign('idBrazilState', 'fk_brazil_municipality_state')
                ->references('id')
                ->on('brazilState')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('postalCode', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idCountry');
            $table->string('normalizedValue', 32);
            $table->string('displayValue', 32);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idCountry', 'normalizedValue'], 'uk_postal_code_country_normalized_value');
            $table->foreign('idCountry', 'fk_postal_code_country')
                ->references('id')
                ->on('country')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('localityReference', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idCountry');
            $table->unsignedBigInteger('idBrazilState')->nullable();
            $table->unsignedBigInteger('idBrazilMunicipality')->nullable();
            $table->string('localityKind', 32);
            $table->string('streetType', 80)->nullable();
            $table->string('streetName', 255)->nullable();
            $table->string('neighborhoodName', 160)->nullable();
            $table->string('cityNameObserved', 160)->nullable();
            $table->string('stateCodeObserved', 32)->nullable();
            $table->string('status', 16);
            $table->string('removalReason', 40)->nullable();
            $table->dateTime('removedAt', 6)->nullable();
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index('localityKind', 'idx_locality_reference_kind');
            $table->index('status', 'idx_locality_reference_status');
            $table->foreign('idCountry', 'fk_locality_reference_country')
                ->references('id')
                ->on('country')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idBrazilState', 'fk_locality_reference_brazil_state')
                ->references('id')
                ->on('brazilState')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('idBrazilMunicipality', 'fk_locality_reference_brazil_municipality')
                ->references('id')
                ->on('brazilMunicipality')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::create('postalCodeLocalityReference', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('idPostalCode');
            $table->unsignedBigInteger('idLocalityReference');
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->primary(['idPostalCode', 'idLocalityReference'], 'pk_postal_code_locality_reference');
            $table->foreign('idPostalCode', 'fk_postal_code_locality_reference_postal_code')
                ->references('id')
                ->on('postalCode')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idLocalityReference', 'fk_postal_code_locality_reference_locality_reference')
                ->references('id')
                ->on('localityReference')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('localityReferenceObservation', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idLocalityReference');
            $table->string('sourceKey', 64);
            $table->string('externalIdentifier', 255)->nullable();
            $table->char('identitySignature', 64);
            $table->char('equivalenceSignature', 64)->nullable();
            $table->json('observedPayload');
            $table->dateTime('firstSeenAt', 6);
            $table->dateTime('lastSeenAt', 6);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['sourceKey', 'identitySignature'], 'uk_locality_observation_source_signature');
            $table->index('equivalenceSignature', 'idx_locality_observation_equivalence_signature');
            $table->index('lastSeenAt', 'idx_locality_observation_last_seen');
            $table->foreign('idLocalityReference', 'fk_locality_observation_locality_reference')
                ->references('id')
                ->on('localityReference')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Drop the global territorial and postal-reference catalogs.
     */
    public function down(): void
    {
        Schema::dropIfExists('localityReferenceObservation');
        Schema::dropIfExists('postalCodeLocalityReference');
        Schema::dropIfExists('localityReference');
        Schema::dropIfExists('postalCode');
        Schema::dropIfExists('brazilMunicipality');
        Schema::dropIfExists('brazilState');
        Schema::dropIfExists('country');
    }
};
