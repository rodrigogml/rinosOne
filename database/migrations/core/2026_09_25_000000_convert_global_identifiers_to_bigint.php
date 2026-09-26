<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert the persisted global graph from textual identifiers to BIGINT keys.
     *
     * Existing records are preserved by mapping every old identifier to an auto-incremented
     * numeric identifier before replacing foreign keys and primary keys.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || Schema::getColumnType('user', 'id') === 'bigint') {
            return;
        }

        foreach (['user', 'persistentAuthentication', 'authenticationChallenge', 'tenant', 'tenantMembership', 'tenantProvisioning'] as $table) {
            DB::statement("ALTER TABLE `{$table}` ADD COLUMN `temporaryId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE INDEX `uk_{$table}_temporary_id` (`temporaryId`)");
        }

        DB::statement('ALTER TABLE `persistentAuthentication` ADD COLUMN `temporaryIdUser` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `authenticationChallenge` ADD COLUMN `temporaryIdUser` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `session` ADD COLUMN `temporaryIdUser` BIGINT UNSIGNED NULL, ADD COLUMN `temporaryIdPersistentAuthentication` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `tenantMembership` ADD COLUMN `temporaryIdTenant` BIGINT UNSIGNED NULL, ADD COLUMN `temporaryIdUser` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `tenantProvisioning` ADD COLUMN `temporaryIdTenant` BIGINT UNSIGNED NULL, ADD COLUMN `temporaryIdRequestedByUser` BIGINT UNSIGNED NULL');

        DB::statement('UPDATE `persistentAuthentication` child JOIN `user` parent ON child.`idUser` = parent.`id` SET child.`temporaryIdUser` = parent.`temporaryId`');
        DB::statement('UPDATE `authenticationChallenge` child JOIN `user` parent ON child.`idUser` = parent.`id` SET child.`temporaryIdUser` = parent.`temporaryId`');
        DB::statement('UPDATE `session` child LEFT JOIN `user` parent ON child.`idUser` = parent.`id` SET child.`temporaryIdUser` = parent.`temporaryId`');
        DB::statement('UPDATE `session` child LEFT JOIN `persistentAuthentication` parent ON child.`idPersistentAuthentication` = parent.`id` SET child.`temporaryIdPersistentAuthentication` = parent.`temporaryId`');
        DB::statement('UPDATE `tenantMembership` child JOIN `tenant` parent ON child.`idTenant` = parent.`id` SET child.`temporaryIdTenant` = parent.`temporaryId`');
        DB::statement('UPDATE `tenantMembership` child JOIN `user` parent ON child.`idUser` = parent.`id` SET child.`temporaryIdUser` = parent.`temporaryId`');
        DB::statement('UPDATE `tenantProvisioning` child JOIN `tenant` parent ON child.`idTenant` = parent.`id` SET child.`temporaryIdTenant` = parent.`temporaryId`');
        DB::statement('UPDATE `tenantProvisioning` child JOIN `user` parent ON child.`idRequestedByUser` = parent.`id` SET child.`temporaryIdRequestedByUser` = parent.`temporaryId`');

        $this->assertMapped('persistentAuthentication', 'temporaryIdUser');
        $this->assertMapped('authenticationChallenge', 'temporaryIdUser');
        $this->assertMapped('tenantMembership', 'temporaryIdTenant');
        $this->assertMapped('tenantMembership', 'temporaryIdUser');
        $this->assertMapped('tenantProvisioning', 'temporaryIdTenant');
        $this->assertMapped('tenantProvisioning', 'temporaryIdRequestedByUser');

        foreach ([
            ['authenticationChallenge', 'fk_authentication_challenge_user'],
            ['persistentAuthentication', 'fk_persistent_authentication_user'],
            ['session', 'fk_session_persistent_authentication'],
            ['session', 'fk_session_user'],
            ['tenantMembership', 'fk_tenant_membership_tenant'],
            ['tenantMembership', 'fk_tenant_membership_user'],
            ['tenantProvisioning', 'fk_tenant_provisioning_requested_user'],
            ['tenantProvisioning', 'fk_tenant_provisioning_tenant'],
        ] as [$table, $foreignKey]) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
        }

        foreach ([
            ['persistentAuthentication', 'idx_persistent_authentication_user'],
            ['authenticationChallenge', 'uk_authentication_challenge_user_purpose'],
            ['session', 'idx_session_user'],
            ['session', 'fk_session_persistent_authentication'],
            ['tenantMembership', 'uk_tenant_membership_tenant_user'],
            ['tenantMembership', 'idx_tenant_membership_user_state'],
            ['tenantMembership', 'idx_tenant_membership_recent_context'],
            ['tenantProvisioning', 'uk_tenant_provisioning_tenant'],
            ['tenantProvisioning', 'uk_tenant_provisioning_request_intent'],
        ] as [$table, $index]) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }

        foreach (['user', 'persistentAuthentication', 'authenticationChallenge', 'tenant', 'tenantMembership', 'tenantProvisioning'] as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, DROP COLUMN `id`, CHANGE `temporaryId` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`), DROP INDEX `uk_{$table}_temporary_id`");
        }

        DB::statement('ALTER TABLE `persistentAuthentication` DROP COLUMN `idUser`, CHANGE `temporaryIdUser` `idUser` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `authenticationChallenge` DROP COLUMN `idUser`, CHANGE `temporaryIdUser` `idUser` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `session` DROP COLUMN `idUser`, DROP COLUMN `idPersistentAuthentication`, CHANGE `temporaryIdUser` `idUser` BIGINT UNSIGNED NULL, CHANGE `temporaryIdPersistentAuthentication` `idPersistentAuthentication` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `tenantMembership` DROP COLUMN `idTenant`, DROP COLUMN `idUser`, CHANGE `temporaryIdTenant` `idTenant` BIGINT UNSIGNED NOT NULL, CHANGE `temporaryIdUser` `idUser` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `tenantProvisioning` DROP COLUMN `idTenant`, DROP COLUMN `idRequestedByUser`, CHANGE `temporaryIdTenant` `idTenant` BIGINT UNSIGNED NOT NULL, CHANGE `temporaryIdRequestedByUser` `idRequestedByUser` BIGINT UNSIGNED NOT NULL, MODIFY `idempotencyKey` CHAR(36) NOT NULL');

        DB::statement('ALTER TABLE `persistentAuthentication` ADD INDEX `idx_persistent_authentication_user` (`idUser`, `revokedAt`), ADD CONSTRAINT `fk_persistent_authentication_user` FOREIGN KEY (`idUser`) REFERENCES `user` (`id`) ON UPDATE CASCADE ON DELETE CASCADE');
        DB::statement('ALTER TABLE `authenticationChallenge` ADD UNIQUE INDEX `uk_authentication_challenge_user_purpose` (`idUser`, `purpose`), ADD CONSTRAINT `fk_authentication_challenge_user` FOREIGN KEY (`idUser`) REFERENCES `user` (`id`) ON UPDATE CASCADE ON DELETE CASCADE');
        DB::statement('ALTER TABLE `session` ADD INDEX `idx_session_user` (`idUser`), ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`idUser`) REFERENCES `user` (`id`) ON UPDATE CASCADE ON DELETE CASCADE, ADD CONSTRAINT `fk_session_persistent_authentication` FOREIGN KEY (`idPersistentAuthentication`) REFERENCES `persistentAuthentication` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
        DB::statement('ALTER TABLE `tenantMembership` ADD UNIQUE INDEX `uk_tenant_membership_tenant_user` (`idTenant`, `idUser`), ADD INDEX `idx_tenant_membership_user_state` (`idUser`, `state`), ADD INDEX `idx_tenant_membership_recent_context` (`idUser`, `state`, `lastContextSelectedAt`), ADD CONSTRAINT `fk_tenant_membership_tenant` FOREIGN KEY (`idTenant`) REFERENCES `tenant` (`id`) ON UPDATE CASCADE ON DELETE CASCADE, ADD CONSTRAINT `fk_tenant_membership_user` FOREIGN KEY (`idUser`) REFERENCES `user` (`id`) ON UPDATE CASCADE ON DELETE CASCADE');
        DB::statement('ALTER TABLE `tenantProvisioning` ADD UNIQUE INDEX `uk_tenant_provisioning_tenant` (`idTenant`), ADD UNIQUE INDEX `uk_tenant_provisioning_request_intent` (`idRequestedByUser`, `idempotencyKey`), ADD CONSTRAINT `fk_tenant_provisioning_tenant` FOREIGN KEY (`idTenant`) REFERENCES `tenant` (`id`) ON UPDATE CASCADE ON DELETE CASCADE, ADD CONSTRAINT `fk_tenant_provisioning_requested_user` FOREIGN KEY (`idRequestedByUser`) REFERENCES `user` (`id`) ON UPDATE CASCADE ON DELETE CASCADE');
    }

    public function down(): void
    {
        throw new LogicException('The conversion of global identifiers to BIGINT is irreversible.');
    }

    private function assertMapped(string $table, string $column): void
    {
        if (DB::table($table)->whereNull($column)->exists()) {
            throw new LogicException("Could not preserve every reference in {$table}.{$column}.");
        }
    }
};
