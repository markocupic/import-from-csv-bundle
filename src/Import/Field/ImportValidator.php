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
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
    ) {
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    public function checkIsValidDate(Widget $objWidget, array $arrDca): void
    {
        $varValue = $objWidget->value;
        $rgxp = $arrDca['eval']['rgxp'] ?? null;

        if (!$rgxp || !\strlen((string) $varValue)) {
            return;
        }

        if ('date' === $rgxp || 'datim' === $rgxp || 'time' === $rgxp) {
            if (!$this->validator->{'is'.ucfirst($rgxp)}($varValue)) {
                $objWidget->addError(
                    sprintf(
                        $this->translator->trans('ERR.invalidDate', [], 'contao_default'),
                        $objWidget->value,
                    )
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    public function checkIsUnique(Widget $objWidget, array $arrDca): void
    {
        // Make sure that unique fields are unique
        if (isset($arrDca['eval']['unique']) && true === $arrDca['eval']['unique']) {
            $varValue = $objWidget->value;

            if (\strlen((string) $varValue)) {
                $query = sprintf(
                    'SELECT id FROM %s WHERE %s = ?',
                    $objWidget->strTable,
                    $objWidget->strField,
                );

                if ($this->connection->fetchOne($query, [$varValue])) {
                    $objWidget->addError(
                        sprintf(
                            $this->translator->trans('ERR.unique', [], 'contao_default'),
                            $objWidget->strField,
                        )
                    );
                }
            }
        }
    }
}
