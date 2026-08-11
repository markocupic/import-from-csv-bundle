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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class PostImportBatchEvent extends Event
{
    public function __construct(
        private readonly ImportFromCsv $importInstance,
        private readonly Request $request,
        private readonly array $importData,
    ) {
    }

    public function getImportInstance(): ImportFromCsv
    {
        return $this->importInstance;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getImportData(): array
    {
        return $this->importData;
    }
}
