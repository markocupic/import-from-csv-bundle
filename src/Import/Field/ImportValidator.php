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

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Validator;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportValidator
{
    private readonly Adapter $validator;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    public function checkIsValidDate(Widget $widget, array $dca): void
    {
        $value = $widget->value;
        $rgxp = $dca['eval']['rgxp'] ?? null;

        if (!$rgxp || !\strlen((string) $value)) {
            return;
        }

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            if (!$this->validator->{'is'.ucfirst($rgxp)}($value)) {
                $widget->addError(
                    \sprintf(
                        $this->translator->trans('ERR.invalidDate', [], 'contao_default'),
                        $widget->value,
                    ),
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    public function checkIsUnique(Widget $widget, array $dca): void
    {
        // Make sure that unique fields are unique
        if (isset($dca['eval']['unique']) && true === $dca['eval']['unique']) {
            $value = $widget->value;

            if (\strlen((string) $value)) {
                $query = \sprintf(
                    'SELECT id FROM %s WHERE %s = ?',
                    $widget->strTable,
                    $widget->strField,
                );

                if ($this->connection->fetchOne($query, [$value])) {
                    $widget->addError(
                        \sprintf(
                            $this->translator->trans('ERR.unique', [], 'contao_default'),
                            $widget->strField,
                        ),
                    );
                }
            }
        }
    }
}
