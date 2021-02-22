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
    private $tablename;

    private $name;

    private $record;

    private $value;

    private $dca = [];

    private $inputType = 'text';

    private $widget;

    private $skipWidgetValidation = false;

    private $arrErrors = [];

    private $doNotSave = false;

    public function __construct()
    {
    }

    /**
     * @return $this
     */
    public function create(string $tablename, string $name, array $record): self
    {
        $this->tablename = $tablename;
        $this->name = $name;
        $this->record = $record;

        return $this;
    }

    public function getTablename(): string
    {
        return $this->tablename;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRecord(): array
    {
        return $this->record;
    }

    public function getValue(): ?string
    {
        return $this->fieldvalue;
    }

    public function getDoNotSave(): bool
    {
        return $this->doNotSave;
    }

    public function getDca(): array
    {
        return $this->dca;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function getWidget(): Widget
    {
        return $this->widget;
    }

    public function getSkipWidgetValidation(): bool
    {
        return $this->skipWidgetValidation;
    }

    public function getErrors(): array
    {
        return $this->arrErrors;
    }

    public function setValue(?string $value): void
    {
        $this->fieldvalue = $value;

        if ($this->widget) {
            $this->widget->value = $value;
        }
    }

    public function setDca(array $dca): void
    {
        $this->dca = $dca;
    }

    public function setInputType(string $inputType): void
    {
        $this->inputType = $inputType;

        if ($this->widget) {
            $this->widget->inputType = $inputType;
        }
    }

    public function setWidget(Widget $widget): void
    {
        $this->widget = $widget;
    }

    public function setSkipWidgetValidation(bool $skip): void
    {
        $this->skipWidgetValidation = $skip;
    }

    public function setDoNotSave(bool $doNotSave): void
    {
        $this->doNotSave = $doNotSave;

        if ($this->widget) {
            $this->widget->doNotSave = $doNotSave;
        }
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
