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

use Contao\Widget;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class PreValidateWidgetEvent extends Event
{
    public const string NAME = 'import_from_csv.pre_validate_widget';

    public function __construct(
        private readonly Widget $widget,
        private readonly array $csvRecord,
        private readonly ImportFromCsv $importInstance,
        private readonly Request|null $request = null,
    ) {
    }

    public function getWidget(): Widget
    {
        return $this->widget;
    }

    public function getCsvRecord(): array
    {
        return $this->csvRecord;
    }

    public function getImportInstance(): ImportFromCsv
    {
        return $this->importInstance;
    }

    public function getRequest(): Request|null
    {
        return $this->request;
    }
}
