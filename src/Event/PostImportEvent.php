<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Event;

use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;
use Symfony\Contracts\EventDispatcher\Event;

class PostImportEvent extends Event
{
    public const NAME = 'import_from_csv.post_import';

    public function __construct(
        private readonly string $tableName,
        private readonly array $dataRecord,
        private readonly int $insertId,
        private readonly array $arrLine,
        private readonly ImportFromCsv $importInstance,
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
        return $this->arrLine;
    }

    public function getImportInstance(): ImportFromCsv
    {
        return $this->importInstance;
    }
}
