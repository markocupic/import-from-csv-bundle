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

namespace Markocupic\ImportFromCsvBundle\Import\Field;

use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Widget;

class Formatter
{
    private readonly Adapter $stringUtil;

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    public function getCorrectDateFormat(mixed $varValue, array $arrDca): mixed
    {
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) && '' !== $varValue) {
            $configAdapter = $this->framework->getAdapter(Config::class);
            $df = $configAdapter->get($rgxp.'Format');

            if (false !== ($tstamp = strtotime((string) $varValue))) {
                $varValue = date($df, $tstamp);
            }
        }

        return $varValue;
    }

    public function convertToArray(mixed $varValue, array $arrDca, string $strArrDelim): mixed
    {
        if (!\is_array($varValue) && isset($arrDca['eval']['multiple']) && $arrDca['eval']['multiple']) {
            // Convert CSV fields
            if (isset($arrDca['eval']['csv'])) {
                if (null === $varValue || '' === $varValue) {
                    $varValue = [];
                } else {
                    $varValue = explode($arrDca['eval']['csv'], (string) $varValue);
                }
            } elseif (str_contains((string) $varValue, $strArrDelim)) {
                // Value is e.g. 3||4
                $varValue = explode($strArrDelim, (string) $varValue);
            } else {
                // The value is a serialized array or simple value e.g 3
                $varValue = $this->stringUtil->deserialize($varValue, true);
            }
        }

        return $varValue;
    }

    public function convertDateToTimestamp(Widget $objWidget, array $arrDca): mixed
    {
        $varValue = $objWidget->value;
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if ('tstamp' === $objWidget->name && !empty($varValue)) {
            if (false !== ($tstamp = strtotime((string) $varValue))) {
                return $tstamp;
            }
        }

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

    public function replaceNewlineTags(mixed $varValue): mixed
    {
        if (\is_string($varValue)) {
            // Replace all '[NEWLINE]' tags with the end of line tag
            $varValue = str_replace('[NEWLINE]', PHP_EOL, $varValue);
        }

        return $varValue;
    }
}
