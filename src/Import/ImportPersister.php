<?php

declare(strict_types=1);

namespace Markocupic\ImportFromCsvBundle\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class ImportPersister
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $set
     *
     * @throws Exception
     */
    public function upsert(string $tableName, mixed $primaryKey, array $set): int
    {
        $pkValue = $this->normalizePrimaryKeyValue($set[$primaryKey] ?? null);

        $set = $this->quoteKeys($set);

        if (null !== $pkValue) {
            $data = $this->removePrimaryKey($primaryKey, $set);

            if ([] === $data) {
                throw new \InvalidArgumentException('No updatable fields provided.');
            }

            $this->connection->update($tableName, $data, [$primaryKey => $pkValue]);

            return $pkValue;
        }

        $this->connection->insert($tableName, $set);

        return (int) $this->connection->lastInsertId();
    }

    private function normalizePrimaryKeyValue(mixed $value): int|null
    {
        if (null === $value) {
            return null;
        }

        if (\is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (\is_string($value) && '' !== trim($value) && ctype_digit(trim($value))) {
            $int = (int) trim($value);

            return $int > 0 ? $int : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $set
     *
     * @return array<string, mixed>
     */
    private function removePrimaryKey(string $primaryKey, array $set): array
    {
        unset($set[$primaryKey]);

        return $set;
    }

    private function quoteKeys(array $csvRecord): array
    {
        $quotedRecord = [];

        foreach ($csvRecord as $k => $v) {
            $quotedRecord[$this->connection->quoteIdentifier($k)] = $v;
        }

        return $quotedRecord;
    }
}
