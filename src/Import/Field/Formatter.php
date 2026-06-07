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

namespace Markocupic\ImportFromCsvBundle\Import\Field;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Widget;

class Formatter
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    public function getCorrectDateFormat(mixed $value, array $dca): mixed
    {
        $rgxp = $dca['eval']['rgxp'] ?? null;

        if (!\is_string($value) || !\strlen($value)) {
            return $value;
        }

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            $df = $this->framework->getAdapter(Config::class)->get($rgxp.'Format');

            if (false !== ($tstamp = strtotime($value))) {
                $value = date($df, $tstamp);
            }
        }

        return $value;
    }

    public function convertToArray(mixed $value, array $dca, string $delimiter): mixed
    {
        if (!\is_array($value) && isset($dca['eval']['multiple']) && $dca['eval']['multiple']) {
            // Convert CSV fields
            if (isset($dca['eval']['csv'])) {
                if (null === $value || '' === $value) {
                    $value = [];
                } else {
                    $value = explode($dca['eval']['csv'], (string) $value);
                }
            } elseif (str_contains((string) $value, $delimiter)) {
                // Value is e.g. 3||4
                $value = explode($delimiter, (string) $value);
            } else {
                // The value is a serialized array or simple value e.g., 3
                $value = $this->framework->getAdapter(StringUtil::class)->deserialize($value, true);
            }
        }

        return $value;
    }

    public function strtotime(Widget $widget, array $dca): mixed
    {
        $value = $widget->value;
        $rgxp = $dca['eval']['rgxp'] ?? null;

        if ('tstamp' === $widget->name && \is_string($value) && \strlen($value)) {
            if (false !== ($tstamp = strtotime($value))) {
                return $tstamp;
            }
        }

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            $value = trim((string) $value);

            if (empty($value)) {
                return null;
            }

            if (false !== ($tstamp = strtotime($value))) {
                return $tstamp;
            }

            $widget->addError(\sprintf('Invalid value "%s" set for field "%s.%s".', $value, $widget->strTable, $widget->strField));
        }

        return $value;
    }

    public function replaceNewlineTags(mixed $value): mixed
    {
        if (\is_string($value)) {
            // Replace all '[NEWLINE]' tags with the end-of-line tag
            $value = str_replace('[NEWLINE]', PHP_EOL, $value);
        }

        return $value;
    }
}
