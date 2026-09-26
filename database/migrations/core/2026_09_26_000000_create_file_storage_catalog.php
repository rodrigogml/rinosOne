<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the global catalog used by private file storage consumers.
     */
    public function up(): void
    {
        Schema::create('file_file', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('fileUuid');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique('fileUuid', 'uk_file_file_uuid');
        });

        Schema::create('file_fileContent', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->char('logicalSha256', 64);
            $table->unsignedBigInteger('logicalSizeBytes');
            $table->string('detectedMimeType', 255);
            $table->string('declaredExtension', 32)->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique('logicalSha256', 'uk_file_content_logical_sha256');
        });

        Schema::create('file_fileVersion', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idFile');
            $table->unsignedBigInteger('idParentFileVersion')->nullable();
            $table->unsignedBigInteger('idFileContent');
            $table->unsignedInteger('versionNumber');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idFile', 'versionNumber'], 'uk_file_version_file_number');
            $table->index('idParentFileVersion', 'idx_file_version_parent');
            $table->foreign('idFile', 'fk_file_version_file')
                ->references('id')
                ->on('file_file')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idParentFileVersion', 'fk_file_version_parent')
                ->references('id')
                ->on('file_fileVersion')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('idFileContent', 'fk_file_version_content')
                ->references('id')
                ->on('file_fileContent')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('file_storageBackend', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('backendKey', 120);
            $table->enum('state', ['ACTIVE', 'READ_ONLY', 'INACTIVE']);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique('backendKey', 'uk_file_storage_backend_key');
        });

        Schema::create('file_storageObject', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idFileContent');
            $table->unsignedBigInteger('idStorageBackend');
            $table->char('storedSha256', 64);
            $table->string('storageKey', 512);
            $table->enum('encoding', ['IDENTITY', 'GZIP']);
            $table->unsignedBigInteger('storedSizeBytes');
            $table->enum('state', ['WRITING', 'ACTIVE', 'RETIRED', 'ORPHANED']);
            $table->timestamp('retentionUntil')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idStorageBackend', 'storedSha256', 'encoding'], 'uk_file_storage_object_backend_hash_encoding');
            $table->unique(['idStorageBackend', 'storageKey'], 'uk_file_storage_object_backend_key');
            $table->index(['state', 'retentionUntil'], 'idx_file_storage_object_state_retention');
            $table->foreign('idFileContent', 'fk_file_storage_object_content')
                ->references('id')
                ->on('file_fileContent')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idStorageBackend', 'fk_file_storage_object_backend')
                ->references('id')
                ->on('file_storageBackend')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('file_filePossession', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idFile');
            $table->unsignedBigInteger('idCurrentFileVersion');
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->enum('storageArea', ['WORKSPACE', 'SYSTEM_MANAGED']);
            $table->string('purpose', 120)->nullable();
            $table->string('displayName', 255);
            $table->enum('state', ['ACTIVE', 'TRASHED', 'RELEASED']);
            $table->unsignedBigInteger('logicalSizeBytes');
            $table->timestamp('trashedAt')->nullable();
            $table->timestamp('purgeAfter')->nullable();
            $table->timestamp('releasedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idUser', 'state'], 'idx_file_possession_user_state');
            $table->index(['idTenant', 'state'], 'idx_file_possession_tenant_state');
            $table->index(['storageArea', 'purpose'], 'idx_file_possession_area_purpose');
            $table->index(['state', 'purgeAfter'], 'idx_file_possession_state_purge');
            $table->foreign('idFile', 'fk_file_possession_file')
                ->references('id')
                ->on('file_file')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idCurrentFileVersion', 'fk_file_possession_current_version')
                ->references('id')
                ->on('file_fileVersion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('idUser', 'fk_file_possession_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_file_possession_tenant')
                ->references('id')
                ->on('tenant')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('file_systemBinding', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idUser');
            $table->string('bindingKey', 120);
            $table->unsignedBigInteger('idFilePossession')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idUser', 'bindingKey'], 'uk_file_system_binding_user_key');
            $table->foreign('idUser', 'fk_file_system_binding_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idFilePossession', 'fk_file_system_binding_possession')
                ->references('id')
                ->on('file_filePossession')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::create('file_ownerUsage', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->unsignedBigInteger('workspaceBytes')->default(0);
            $table->unsignedBigInteger('systemManagedBytes')->default(0);
            $table->unsignedBigInteger('trashBytes')->default(0);
            $table->unsignedBigInteger('totalBytes')->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique('idUser', 'uk_file_owner_usage_user');
            $table->unique('idTenant', 'uk_file_owner_usage_tenant');
            $table->foreign('idUser', 'fk_file_owner_usage_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_file_owner_usage_tenant')
                ->references('id')
                ->on('tenant')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('file_versionMetadata', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idFileVersion');
            $table->string('metadataKey', 120);
            $table->json('metadataValue');
            $table->enum('source', ['EXTRACTED', 'DECLARED']);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['idFileVersion', 'metadataKey'], 'idx_file_version_metadata_version_key');
            $table->foreign('idFileVersion', 'fk_file_version_metadata_version')
                ->references('id')
                ->on('file_fileVersion')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('file_versionDerivative', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idSourceFileVersion');
            $table->unsignedBigInteger('idFileContent');
            $table->string('derivativeKind', 80);
            $table->string('derivativeKey', 160);
            $table->enum('state', ['PENDING', 'ACTIVE', 'FAILED', 'RETIRED']);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idSourceFileVersion', 'derivativeKind', 'derivativeKey'], 'uk_file_version_derivative_source_kind_key');
            $table->foreign('idSourceFileVersion', 'fk_file_version_derivative_source')
                ->references('id')
                ->on('file_fileVersion')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idFileContent', 'fk_file_version_derivative_content')
                ->references('id')
                ->on('file_fileContent')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // Owner exclusivity is enforced by the Eloquent models. MySQL 9 rejects
        // these CHECK expressions when their columns also use cascading FKs.
    }

    /**
     * Drop the global private file storage catalog.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_versionDerivative');
        Schema::dropIfExists('file_versionMetadata');
        Schema::dropIfExists('file_ownerUsage');
        Schema::dropIfExists('file_systemBinding');
        Schema::dropIfExists('file_filePossession');
        Schema::dropIfExists('file_storageObject');
        Schema::dropIfExists('file_storageBackend');
        Schema::dropIfExists('file_fileVersion');
        Schema::dropIfExists('file_fileContent');
        Schema::dropIfExists('file_file');
    }
};
