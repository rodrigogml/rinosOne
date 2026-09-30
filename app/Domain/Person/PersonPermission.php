<?php

namespace App\Domain\Person;

/**
 * Defines the stable tenant-scoped authorization keys of the People module.
 */
final class PersonPermission
{
    public const READ = 'tenant.people.read';

    public const CREATE = 'tenant.people.create';

    public const UPDATE = 'tenant.people.update';

    public const DUPLICATE = 'tenant.people.duplicate';

    public const INACTIVATE = 'tenant.people.inactivate';

    public const REACTIVATE = 'tenant.people.reactivate';

    public const DELETE = 'tenant.people.delete';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::READ,
            self::CREATE,
            self::UPDATE,
            self::DUPLICATE,
            self::INACTIVATE,
            self::REACTIVATE,
            self::DELETE,
        ];
    }
}
