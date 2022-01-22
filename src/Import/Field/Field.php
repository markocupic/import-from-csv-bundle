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

    /**
     * @var bool
     */
    private $doNotSave = false;

    /**
     * @return $this
     */
    public function create(string $tableName, string $name, array $record): self
    {
        $this->tableName = $tableName;
        $this->name = $name;
        $this->record = $record;

        return $this;
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
    public function getValue()    {
        return $this->value;
    }

    public function getDoNotSave(): bool
    {
        return $this->doNotSave;
    }

    public function getDca(): array
    {
        return $this->dca;
    }

    public function getWidget():?Widget
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

    public function setDoNotSave(bool $doNotSave): void
    {
        $this->doNotSave = $doNotSave;
    }

    public function addError(string $msg): void
    {
        $this->arrError[] = $msg;
    }

    public function hasErrors(): bool
    {
        return !empty($this->arrErrors);
    }
}
