<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_permission', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('key', 160)->unique('uk_auth_permission_key');
            $table->string('displayName', 160);
            $table->string('scope', 16);
            $table->boolean('systemManaged')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('auth_role', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('key', 160)->unique('uk_auth_role_key');
            $table->string('displayName', 160);
            $table->string('scope', 16);
            $table->string('type', 16);
            $table->boolean('systemManaged')->default(false);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('auth_role_permission', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('idRole');
            $table->unsignedBigInteger('idPermission');
            $table->primary(['idRole', 'idPermission'], 'pk_auth_role_permission');
            $table->foreign('idRole', 'fk_auth_role_permission_role')->references('id')->on('auth_role')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idPermission', 'fk_auth_role_permission_permission')->references('id')->on('auth_permission')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('auth_role_assignment', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('idRole');
            $table->unsignedBigInteger('idUser');
            $table->unsignedBigInteger('idTenant')->nullable();
            $table->string('state', 16);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['idRole', 'idUser', 'idTenant'], 'uk_auth_role_assignment_role_user_tenant');
            $table->index(['idUser', 'idTenant', 'state'], 'idx_auth_role_assignment_user_tenant_state');
            $table->foreign('idRole', 'fk_auth_role_assignment_role')->references('id')->on('auth_role')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idUser', 'fk_auth_role_assignment_user')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idTenant', 'fk_auth_role_assignment_tenant')->references('id')->on('tenant')->cascadeOnUpdate()->cascadeOnDelete();
        });

        $now = now();
        $permissionId = DB::table('auth_permission')->insertGetId([
            'key' => 'tenant.availability.manage',
            'displayName' => 'Manage tenant availability',
            'scope' => 'TENANT',
            'systemManaged' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
        $roleId = DB::table('auth_role')->insertGetId([
            'key' => 'tenant.administrator',
            'displayName' => 'Tenant administrator',
            'scope' => 'TENANT',
            'type' => 'SYSTEM',
            'systemManaged' => true,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
        DB::table('auth_role_permission')->insert(['idRole' => $roleId, 'idPermission' => $permissionId]);

        DB::table('tenantMembership')->orderBy('id')->each(function (object $membership) use ($roleId, $now): void {
            DB::table('auth_role_assignment')->insert([
                'idRole' => $roleId,
                'idUser' => $membership->idUser,
                'idTenant' => $membership->idTenant,
                'state' => 'ACTIVE',
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        });

        if (Schema::hasColumn('tenantMembership', 'role')) {
            Schema::table('tenantMembership', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_role_assignment');
        Schema::dropIfExists('auth_role_permission');
        Schema::dropIfExists('auth_role');
        Schema::dropIfExists('auth_permission');
    }
};
