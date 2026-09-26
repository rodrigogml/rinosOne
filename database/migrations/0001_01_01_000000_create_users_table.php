<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('email')->unique('uk_user_email');
            $table->string('displayName')->nullable();
            $table->string('passwordHash')->nullable();
            $table->timestamp('emailVerifiedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('persistentAuthentication', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idUser');
            $table->string('secretHash')->unique('uk_persistent_authentication_secret_hash');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('revokedAt')->nullable();
            $table->timestamp('lastUsedAt')->useCurrent();
            $table->index(['idUser', 'revokedAt'], 'idx_persistent_authentication_user');
            $table->foreign('idUser', 'fk_persistent_authentication_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('session', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('id')->primary();
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idPersistentAuthentication')->nullable();
            $table->longText('payload');
            $table->timestamp('lastActivityAt')->useCurrent();
            $table->index('idUser', 'idx_session_user');
            $table->index('lastActivityAt', 'idx_session_activity');
            $table->foreign('idUser', 'fk_session_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('idPersistentAuthentication', 'fk_session_persistent_authentication')
                ->references('id')
                ->on('persistentAuthentication')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::create('authenticationChallenge', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idUser');
            $table->enum('purpose', ['email_verification', 'passwordless_login']);
            $table->string('secretHash');
            $table->timestamp('expiresAt');
            $table->timestamp('consumedAt')->nullable();
            $table->timestamp('supersededAt')->nullable();
            $table->unsignedTinyInteger('failedAttempts')->default(0);
            $table->boolean('rememberMeRequested')->default(false);
            $table->timestamp('createdAt')->useCurrent();
            $table->unique(['idUser', 'purpose'], 'uk_authentication_challenge_user_purpose');
            $table->index('expiresAt', 'idx_authentication_challenge_expiry');
            $table->foreign('idUser', 'fk_authentication_challenge_user')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authenticationChallenge');
        Schema::dropIfExists('session');
        Schema::dropIfExists('persistentAuthentication');
        Schema::dropIfExists('user');
    }
};
