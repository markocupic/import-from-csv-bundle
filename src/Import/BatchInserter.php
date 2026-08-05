<?php

declare(strict_types=1);

namespace Markocupic\ImportFromCsvBundle\Import;

use Doctrine\DBAL\Connection;
use Markocupic\ImportFromCsvBundle\Event\PostImportEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class BatchInserter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $batchSize = 25,
    ) {
        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('Batch size must be >= 1.');
        }
    }

    /**
     * @param array<string, mixed> $set
     * @param array<string, mixed> $csvRecord
     */
    public function insertRow(string $tableName, array $set, array $csvRecord, ImportFromCsv $import, bool $dispatchPostImportEvent): ?int
    {
        if (!$this->connection->isTransactionActive()) {
            $this->connection->beginTransaction();
        }

        $this->connection->insert($tableName, $this->quoteKeys($set));
        $insertId = (int) $this->connection->lastInsertId();

        if ($dispatchPostImportEvent && $insertId > 0) {
            $event = new PostImportEvent($tableName, $set, $insertId, $csvRecord, $import);
            $this->eventDispatcher->dispatch($event, PostImportEvent::NAME);
        }

        return $insertId > 0 ? $insertId : null;
    }

    public function commitIfBatchBoundary(int $processedRows): void
    {
        if ($processedRows % $this->batchSize !== 0) {
            return;
        }

        if ($this->connection->isTransactionActive()) {
            $this->connection->commit();
        }
    }

    public function commitRemainder(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->commit();
        }
    }

    public function rollbackIfActive(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    /**
     * @param array<string, mixed> $csvRecord
     *
     * @return array<string, mixed>
     */
    private function quoteKeys(array $csvRecord): array
    {
        $quotedRecord = [];

        foreach ($csvRecord as $k => $v) {
            $quotedRecord[$this->connection->quoteIdentifier((string) $k)] = $v;
        }

        return $quotedRecord;
    }
}
