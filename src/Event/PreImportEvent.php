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

class PreImportEvent extends Event
{
    public const NAME = 'import_from_csv.pre_import';

    public function __construct(
        private readonly string $tableName,
        private array $dataRecord,
        private readonly array $csvRecord,
        private readonly ImportFromCsv $importInstance,
    ) {
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setDataRecord(array $dataRecord): void
    {
        $this->dataRecord = $dataRecord;
    }

    public function getDataRecord(): array
    {
        return $this->dataRecord;
    }

    public function getLineAsArray(): array
    {
        return $this->csvRecord;
    }

    public function getImportInstance(): ImportFromCsv
    {
        return $this->importInstance;
    }
}
