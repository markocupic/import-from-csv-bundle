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

namespace Markocupic\ImportFromCsvBundle\Import\Field;

class FieldFactory
{
    /**
     * @var Field
     */
    private $field;

    public function __construct(Field $field)
    {
        $this->field = $field;
    }

    public function getField(string $tablename, string $fieldname, array $record): Field
    {
        return $this->field->create($tablename, $fieldname, $record);
    }
}
