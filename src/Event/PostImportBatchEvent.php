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
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Contracts\EventDispatcher\Event;

class PostImportBatchEvent extends Event
{
    public const string NAME = 'import_from_csv.post_import_batch';

    public function __construct(
        private readonly ?ImportFromCsvModel $importModel,
        private readonly array $logData,
        private readonly string $taskId,
        private readonly bool $isLastBatch,
    ) {
    }

    public function getImportModel(): ?ImportFromCsvModel
    {
        return $this->importModel;
    }

    public function getLogData(): array
    {
        return $this->logData;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function isLastBatch(): bool
    {
        return $this->isLastBatch;
    }
}
