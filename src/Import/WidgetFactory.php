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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\Widget;
use Symfony\Component\HttpFoundation\RequestStack;

final class WidgetFactory
{
    /**
     * @var array<string, DC_Table>
     */
    private array $dcTableCache = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function create(array $dca, string $columnName, string $tableName, mixed $value): Widget
    {
        $inputType = $dca['inputType'] ?? '';

        $objDca = $this->getDcTable($tableName);

        $widgetClassName = $this->getWidgetClass($inputType);

        $attributes = $this->framework
            ->getAdapter($widgetClassName)
            ->getAttributesFromDca($dca, $columnName, $value, $columnName, $tableName, $objDca)
        ;

        return new $widgetClassName($attributes);
    }

    private function getDcTable(string $tableName): DC_Table|null
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return null;
        }

        if (!isset($this->dcTableCache[$tableName])) {
            /** @var DC_Table $dcTable */
            $dcTable = $this->framework->createInstance(DC_Table::class, [$tableName]);
            $this->dcTableCache[$tableName] = $dcTable;
        }

        return $this->dcTableCache[$tableName];
    }

    private function getWidgetClass(string $inputType): string
    {
        $widgetClassName = $GLOBALS['BE_FFL'][$inputType] ?? '';

        if (!empty($widgetClassName) && class_exists($widgetClassName)) {
            return $widgetClassName;
        }

        return $GLOBALS['BE_FFL']['text'];
    }
}
