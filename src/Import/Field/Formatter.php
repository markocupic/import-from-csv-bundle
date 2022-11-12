<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Import\Field;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Widget;

class Formatter
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param $varValue
     *
     * @return false|mixed|string
     */
    public function getCorrectDateFormat($varValue, array $arrDca)
    {
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) && '' !== $varValue) {
            $configAdapter = $this->framework->getAdapter(Config::class);
            $df = $configAdapter->get($rgxp.'Format');

            if (false !== ($tstamp = strtotime($varValue))) {
                $varValue = date($df, $tstamp);
            }
        }

        return $varValue;
    }

    /**
     * @param $varValue
     *
     * @return array|false|mixed|array<string>
     */
    public function convertToArray($varValue, array $arrDca, string $strArrDelim)
    {
        if (!\is_array($varValue) && isset($arrDca['eval']['multiple']) && $arrDca['eval']['multiple']) {
            // Convert CSV fields
            if (isset($arrDca['eval']['csv'])) {
                if (null === $varValue || '' === $varValue) {
                    $varValue = [];
                } else {
                    $varValue = explode($arrDca['eval']['csv'], $varValue);
                }
            } elseif (false !== strpos($varValue, $strArrDelim)) {
                // Value is e.g. 3||4
                $varValue = explode($strArrDelim, $varValue);
            } else {
                /** @var StringUtil $stringUtilAdapter */
                $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

                // The value is a serialized array or simple value e.g 3
                $varValue = $stringUtilAdapter->deserialize($varValue, true);
            }
        }

        return $varValue;
    }

    /**
     * @param $arrDca
     *
     * @return false|int|mixed|string
     */
    public function convertDateToTimestamp(Widget $objWidget, array $arrDca)
    {
        $varValue = $objWidget->value;
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            $varValue = trim((string) $varValue);

            if (empty($varValue)) {
                return null;
            }

            if (false !== ($tstamp = strtotime($varValue))) {
                return $tstamp;
            }

            $objWidget->addError(sprintf('Invalid value "%s" set for field "%s.%s".', $varValue, $objWidget->strTable, $objWidget->strField));
        }

        return $varValue;
    }

    /**
     * @return false|int|mixed|string|null
     */
    public function getCorrectEmptyValue(Widget $objWidget, array $arrDca)
    {
        $varValue = $objWidget->value;

        // Set the correct empty value
        if ($arrDca && '' === $varValue) {
            $varValue = $objWidget->getEmptyValue();

            // Set the correct empty value
            if (empty($varValue)) {
                /*
                 * Hack Because Contao doesn't handle correct empty string input f.ex username
                 * @see https://github.com/contao/core-bundle/blob/master/src/Resources/contao/library/Contao/Widget.php#L1526-1527
                 */
                if (($arrDca['sql'] ?? null) && '' !== $arrDca['sql']) {
                    $sql = $arrDca['sql'];

                    if (false === strpos($sql, 'NOT NULL')) {
                        if (false !== strpos($sql, 'NULL')) {
                            $varValue = null;
                        }
                    }
                }
            }
        }

        return $varValue;
    }

    public function replaceNewlineTags($varValue)
    {
        if (\is_string($varValue)) {
            // Replace all '[NEWLINE]' tags with the end of line tag
            $varValue = str_replace('[NEWLINE]', PHP_EOL, $varValue);
        }

        return $varValue;
    }
}
