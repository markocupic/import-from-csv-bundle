<?php

declare(strict_types=1);

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\DC_Table;
use Contao\Widget;
use Symfony\Component\HttpFoundation\RequestStack;

final class WidgetFactory
{
    /** @var array<string, DC_Table> */
    private array $dcTableCache = [];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function createWidget(array $dca, string $columnName, string $tableName, mixed $value): Widget
    {
        $inputType = (string) ($dca['inputType'] ?? 'text');

        /** @var array<string, class-string<Widget>> $beFfl */
        $beFfl = $GLOBALS['BE_FFL'] ?? [];

        $class = $beFfl[$inputType] ?? $beFfl['text'] ?? null;

        if (!\is_string($class) || '' === $class || !class_exists($class)) {
            throw new \RuntimeException(sprintf('No valid backend widget class found for inputType "%s".', $inputType));
        }

        $dc = $this->getDcTable($tableName);

        /** @var Widget $widget */
        $widget = new $class($class::getAttributesFromDca($dca, $columnName, $value, $columnName, $tableName, $dc));

        return $widget;
    }

    private function getDcTable(string $tableName): ?DC_Table
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return null;
        }

        return $this->dcTableCache[$tableName] ??= new DC_Table($tableName);
    }
}
