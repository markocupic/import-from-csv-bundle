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

namespace Markocupic\ImportFromCsvBundle;

use Markocupic\ImportFromCsvBundle\DependencyInjection\MarkocupicImportFromCsvExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MarkocupicImportFromCsvBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): MarkocupicImportFromCsvExtension
    {
        return new MarkocupicImportFromCsvExtension();
    }
}
