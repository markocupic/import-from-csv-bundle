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

use Contao\Widget;

class Field
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $record;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var array
     */
    private $dca = [];

    /**
     * @var Widget
     */
    private $widget;

    /**
     * @var string
     */
    private $inputType = 'text';

    /**
     * @var bool
     */
    private $skipWidgetValidation = false;

    /**
     * @var array
     */
    private $arrErrors = [];


    public function __construct(string $tableName, string $name, array $record)
    {
        $this->tableName = $tableName;
        $this->name = $name;
        $this->record = $record;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getDca(): array
    {
        return $this->dca;
    }

    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function getSkipWidgetValidation(): bool
    {
        return $this->skipWidgetValidation;
    }

    public function getErrors(): array
    {
        return $this->arrErrors;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function setDca(array $dca): void
    {
        $this->dca = $dca;
    }

    public function setWidget(?Widget $widget): void
    {
        $this->widget = $widget;
    }

    public function setInputType(string $inputType): void
    {
        $this->inputType = $inputType;
    }

    public function setSkipWidgetValidation(bool $skip): void
    {
        $this->skipWidgetValidation = $skip;
    }

    public function addError(string $msg): void
    {
        $this->arrErrors[] = $msg;
    }

    public function addErrors(array $arrErrors): void
    {
        foreach ($arrErrors as $msg) {
            $this->addError($msg);
        }
    }

    public function hasErrors(): bool
    {
        return !empty($this->arrErrors);
    }

    public function getErrorsAsString($strSeparator = ' '): string
    {
        return implode($strSeparator, $this->arrErrors);
    }
}
