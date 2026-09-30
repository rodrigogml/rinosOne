<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the organizational Person aggregate and its local collections.
     */
    public function up(): void
    {
        $coreSchema = $this->coreSchema();

        Schema::create('person', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->enum('personType', ['PF', 'PJ']);
            $table->string('name', 255);
            $table->string('alias', 255)->nullable();
            $table->string('displayName', 511);
            $table->char('cpf', 11)->nullable();
            $table->char('cnpj', 14)->nullable();
            $table->string('rg', 40)->nullable();
            $table->string('rgIssuer', 60)->nullable();
            $table->string('pisNis', 20)->nullable();
            $table->string('passportNumber', 40)->nullable();
            $table->string('foreignDocumentNumber', 60)->nullable();
            $table->date('birthDate')->nullable();
            $table->date('foundationDate')->nullable();
            $table->mediumText('notes')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedBigInteger('version')->default(1);
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique('cpf', 'uk_person_cpf');
            $table->unique('cnpj', 'uk_person_cnpj');
            $table->index('displayName', 'idx_person_display_name');
            $table->index('name', 'idx_person_name');
            $table->index('alias', 'idx_person_alias');
            $table->index(['status', 'displayName'], 'idx_person_status_display_name');
        });

        Schema::create('personAddress', function (Blueprint $table) use ($coreSchema): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('label', 60);
            $table->enum('addressType', ['RESIDENTIAL', 'COMMERCIAL', 'BILLING', 'DELIVERY', 'BRANCH', 'OTHER']);
            $table->unsignedBigInteger('idCountry');
            $table->unsignedBigInteger('idBrazilState')->nullable();
            $table->unsignedBigInteger('idBrazilMunicipality')->nullable();
            $table->unsignedBigInteger('idLocalityReference')->nullable();
            $table->string('stateText', 120)->nullable();
            $table->string('cityText', 120)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('number', 40)->nullable();
            $table->string('complement', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('postalCode', 24)->nullable();
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index('idPerson', 'idx_person_address_person');
            $table->foreign('idPerson', 'fk_person_address_person')
                ->references('id')->on('person')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idCountry', 'fk_person_address_country')
                ->references('id')->on("{$coreSchema}.country")->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idBrazilState', 'fk_person_address_brazil_state')
                ->references('id')->on("{$coreSchema}.brazilState")->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('idBrazilMunicipality', 'fk_person_address_brazil_municipality')
                ->references('id')->on("{$coreSchema}.brazilMunicipality")->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('idLocalityReference', 'fk_person_address_locality_reference')
                ->references('id')->on("{$coreSchema}.localityReference")->cascadeOnUpdate()->nullOnDelete();
        });

        Schema::create('personContact', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->enum('contactType', ['EMAIL', 'PHONE', 'MOBILE', 'WHATSAPP', 'WEBSITE', 'OTHER']);
            $table->string('value', 512);
            $table->string('normalizedValue', 512);
            $table->string('description', 255)->nullable();
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index('idPerson', 'idx_person_contact_person');
            $table->index('normalizedValue', 'idx_person_contact_normalized_value');
            $table->foreign('idPerson', 'fk_person_contact_person')
                ->references('id')->on('person')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('personBankAccount', function (Blueprint $table) use ($coreSchema): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->string('label', 60);
            $table->unsignedBigInteger('idFinancialInstitution')->nullable();
            $table->enum('accountType', ['CHECKING', 'SAVINGS', 'INVESTMENT', 'SALARY', 'OTHER']);
            $table->string('agency', 32)->nullable();
            $table->string('agencyDigit', 32)->nullable();
            $table->string('accountNumber', 64)->nullable();
            $table->string('accountDigit', 32)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index('idPerson', 'idx_person_bank_account_person');
            $table->foreign('idPerson', 'fk_person_bank_account_person')
                ->references('id')->on('person')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idFinancialInstitution', 'fk_person_bank_account_financial_institution')
                ->references('id')->on("{$coreSchema}.financialInstitution")->cascadeOnUpdate()->nullOnDelete();
        });

        Schema::create('personPixKey', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idPerson');
            $table->enum('keyType', ['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'RANDOM']);
            $table->string('keyValue', 255);
            $table->string('normalizedKeyValue', 255);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idPerson', 'keyType', 'normalizedKeyValue'], 'uk_person_pix_key_person_type_value');
            $table->foreign('idPerson', 'fk_person_pix_key_person')
                ->references('id')->on('person')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('personRelationship', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idSourcePerson');
            $table->unsignedBigInteger('idTargetPerson');
            $table->enum('relationshipType', [
                'CHILD_OF',
                'PARENT_OF',
                'GRANDCHILD_OF',
                'GRANDPARENT_OF',
                'SPOUSE_OF',
                'PARTNER_OF',
                'EMPLOYEE_OF',
                'EMPLOYER_OF',
                'CONTRACTOR_OF',
                'CONTRACTING_PARTY_OF',
                'OTHER',
            ]);
            $table->string('description', 1000)->nullable();
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idSourcePerson', 'idTargetPerson', 'relationshipType'], 'uk_person_relationship_direction_type');
            $table->index('idTargetPerson', 'idx_person_relationship_target');
            $table->foreign('idSourcePerson', 'fk_person_relationship_source')
                ->references('id')->on('person')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTargetPerson', 'fk_person_relationship_target')
                ->references('id')->on('person')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('personAuditEvent', function (Blueprint $table) use ($coreSchema): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->unsignedBigInteger('idActorUser')->nullable();
            $table->enum('action', ['CREATED', 'UPDATED', 'INACTIVATED', 'REACTIVATED', 'DELETED']);
            $table->dateTime('occurredAt', 6);
            $table->string('correlationId', 128)->nullable();
            $table->index('personId', 'idx_person_audit_event_person');
            $table->index('occurredAt', 'idx_person_audit_event_occurred_at');
            $table->index('correlationId', 'idx_person_audit_event_correlation_id');
            $table->foreign('idActorUser', 'fk_person_audit_event_actor_user')
                ->references('id')->on("{$coreSchema}.user")->cascadeOnUpdate()->nullOnDelete();
        });
    }

    /**
     * Drop the organizational Person aggregate in dependency order.
     */
    public function down(): void
    {
        Schema::dropIfExists('personAuditEvent');
        Schema::dropIfExists('personRelationship');
        Schema::dropIfExists('personPixKey');
        Schema::dropIfExists('personBankAccount');
        Schema::dropIfExists('personContact');
        Schema::dropIfExists('personAddress');
        Schema::dropIfExists('person');
    }

    private function coreSchema(): string
    {
        $schema = (string) config('access.schemas.core');

        if (preg_match('/\A[a-z][a-z0-9_]*\z/', $schema) !== 1) {
            throw new LogicException('The core schema configuration is invalid.');
        }

        return $schema;
    }
};
