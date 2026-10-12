<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Event;

use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;
use Symfony\Contracts\EventDispatcher\Event;

class PostImportRowEvent extends Event
{
    public function __construct(
        private readonly string $tableName,
        private readonly array $dataRecord,
        private readonly int $insertId,
        private readonly array $csvRecord,
        private readonly ImportFromCsv $importInstance,
        private readonly array $rawCsvLine = [],
    ) {
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getDataRecord(): array
    {
        return $this->dataRecord;
    }

    public function getInsertId(): int
    {
        return $this->insertId;
    }

    public function getLineAsArray(): array
    {
        return $this->csvRecord;
    }

    /**
     * Returns the unprocessed CSV line with all columns, including those
     * that are not selected for import (values are neither trimmed nor mapped).
     */
    public function getRawLineAsArray(): array
    {
        return $this->rawCsvLine;
    }

    public function getImportInstance(): ImportFromCsv
    {
        return $this->importInstance;
    }
}
