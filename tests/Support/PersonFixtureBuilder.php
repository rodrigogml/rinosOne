<?php

namespace Tests\Support;

use Illuminate\Database\ConnectionInterface;

/**
 * Builds deterministic synthetic People rows for integration and performance
 * tests. It deliberately never accepts or derives real personal data.
 */
final class PersonFixtureBuilder
{
    /** @return array<string, mixed> */
    public static function person(int $id, string $status = 'ACTIVE'): array
    {
        $name = sprintf('Pessoa sintética %06d', $id);

        return [
            'id' => $id,
            'personType' => $id % 2 === 0 ? 'PF' : 'PJ',
            'name' => $name,
            'alias' => null,
            'displayName' => $name,
            'cpf' => null,
            'cnpj' => null,
            'status' => $status,
            'version' => 1,
        ];
    }

    public static function insertPeople(ConnectionInterface $connection, int $count, int $chunkSize = 1000, int $firstId = 1): void
    {
        foreach (array_chunk(range($firstId, $firstId + $count - 1), $chunkSize) as $ids) {
            $connection->table('person')->insert(array_map(static fn (int $id): array => self::person($id), $ids));
        }
    }
}
