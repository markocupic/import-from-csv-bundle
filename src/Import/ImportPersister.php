<?php

declare(strict_types=1);

namespace Markocupic\ImportFromCsvBundle\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class ImportPersister
{
    public function __construct(
        private readonly Connection $connection,
    ) {
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
        $this->connection->beginTransaction();

        try {
            if (null !== $pkValue) {
                $data = $this->removePrimaryKey($primaryKey, $set);

                if ([] === $data) {
                    throw new \InvalidArgumentException('No updatable fields provided.');
                }

                $this->connection->update($tableName, $data, [$primaryKey => $pkValue]);

                $this->connection->commit();

                return $pkValue;
            }

            $this->connection->insert($tableName, $set);
            $insertId = (int) $this->connection->lastInsertId();

            $this->connection->commit();

            return $insertId;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function normalizePrimaryKeyValue(mixed $value): ?int
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
