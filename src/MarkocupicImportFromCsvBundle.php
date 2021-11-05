<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle;

use Markocupic\ImportFromCsvBundle\DependencyInjection\Compiler\AddSessionBagsPass;
use Markocupic\ImportFromCsvBundle\DependencyInjection\MarkocupicImportFromCsvExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MarkocupicImportFromCsvBundle extends Bundle
{
    public function getContainerExtension(): MarkocupicImportFromCsvExtension
    {
        return new MarkocupicImportFromCsvExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddSessionBagsPass());
    }
}
