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

namespace Markocupic\ImportFromCsvBundle\Import;

final class ImportConfig
{
    public function __construct(
        public readonly string $taskId,
        public readonly \SplFileInfo $csvFile,
        public readonly string $tableName,
        public readonly string $primaryKey,
        public readonly string $importMode,
        public readonly string $matchBy,
        public readonly array $selectedFields,
        public readonly array $mapValues,
        public readonly string $delimiter,
        public readonly string $enclosure,
        public readonly string $arrayDelimiter,
        public readonly bool $isTestMode,
        public readonly array $skipValidationFields,
        public readonly int $offset,
        public readonly int $limit,
    ) {
    }

    public function setConfig(self $config): self
    {
        return new self(
            taskId: $config->taskId,
            csvFile: $config->csvFile,
            tableName: $config->tableName,
            primaryKey: $config->primaryKey,
            importMode: $config->importMode,
            matchBy: $config->matchBy,
            selectedFields: $config->selectedFields,
            mapValues: $config->mapValues,
            delimiter: $config->delimiter,
            enclosure: $config->enclosure,
            arrayDelimiter: $config->arrayDelimiter,
            isTestMode: $config->isTestMode,
            skipValidationFields: $config->skipValidationFields,
            offset: $config->offset,
            limit: $config->limit,
        );
    }
}
