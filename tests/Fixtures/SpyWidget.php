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

namespace Markocupic\ImportFromCsvBundle\Tests\Fixtures;

use Contao\Widget;

/**
 * Test double for a Contao widget.
 *
 * - The constructor deliberately does NOT call parent::__construct() to avoid
 *   booting the Contao runtime (System/Controller).
 * - It doubles as a spy for the static Widget::getAttributesFromDca() call that
 *   the WidgetFactory routes through the Contao framework adapter.
 */
class SpyWidget extends Widget
{
    /**
     * Arguments of the last getAttributesFromDca() call (null if never called).
     *
     * @var array<string, mixed>|null
     */
    public static array|null $lastGetAttributesArgs = null;

    /**
     * Attributes the widget was constructed with.
     *
     * @var array<string, mixed>
     */
    public array $capturedAttributes = [];

    public function __construct($arrAttributes = null)
    {
        // No parent::__construct() on purpose (keeps the test free of Contao runtime).
        $this->capturedAttributes = (array) $arrAttributes;
    }

    public function generate()
    {
        return '';
    }

    public static function getAttributesFromDca($arrData, $strName, $varValue = null, $strField = '', $strTable = '', $objDca = null)
    {
        self::$lastGetAttributesArgs = [
            'arrData' => $arrData,
            'strName' => $strName,
            'varValue' => $varValue,
            'strField' => $strField,
            'strTable' => $strTable,
            'objDca' => $objDca,
        ];

        return ['generated' => true];
    }
}
