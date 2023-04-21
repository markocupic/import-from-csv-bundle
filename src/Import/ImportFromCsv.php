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

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\File;
use Contao\Input;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\SyntaxError;
use League\Csv\UnavailableStream;
use Markocupic\ImportFromCsvBundle\Event\PostImportEvent;
use Markocupic\ImportFromCsvBundle\Import\Field\Formatter;
use Markocupic\ImportFromCsvBundle\Import\Field\ImportValidator;
use Markocupic\ImportFromCsvBundle\Logger\ImportLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportFromCsv
{
    private array $arrData = [];
    private int $currentLine = 0;
    private int $insertErrors = 0;
    private int $countProcessedRows = 0;
    private \Exception|null $insertException = null;

    // Adapters
    private Adapter $config;
    private Adapter $controller;
    private Adapter $input;
    private Adapter $system;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Formatter $formatter,
        private readonly ImportLogger $importLogger,
        private readonly RequestStack $requestStack,
        private readonly ImportValidator $importValidator,
        private readonly string $projectDir,
    ) {
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->input = $this->framework->getAdapter(Input::class);
        $this->system = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws \Doctrine\DBAL\Exception
     * @throws SyntaxError
     * @throws UnavailableStream
     */
    public function importCsv(File $objCsvFile, string $tableName, string $strImportMode, array $arrSelectedFields = [], string $strDelimiter = ';', string $strEnclosure = '"', string $strArrayDelimiter = '||', bool $blnTestMode = false, array $arrSkipValidationFields = [], int $intOffset = 0, int $intLimit = 0, string|null $taskId = null): void
    {
        // Generate a taskId, if there is none.
        $taskId = $taskId ?? uniqid();

        $request = $this->requestStack->getCurrentRequest();

        if (!$this->importLogger->hasInitialized($taskId) && $request) {
            $taskId = $this->importLogger->initialize($taskId);
        }

        $this->controller->loadLanguageFile('tl_import_from_csv');

        if ('' === $strDelimiter) {
            $strDelimiter = ';';
        }

        if ('' === $strEnclosure) {
            $strEnclosure = '"';
        }

        if (empty($strArrayDelimiter)) {
            $strArrayDelimiter = '||';
        }

        // Throw an exception if the submitted string length is not equal to 1 byte.
        if (\strlen($strDelimiter) > 1) {
            throw new \Exception(sprintf('%s expects field delimiter to be a single character. %s given.', __METHOD__, $strDelimiter));
        }

        // Throw an exception if the submitted string length is not equal to 1 byte.
        if (\strlen($strEnclosure) > 1) {
            throw new \Exception(sprintf('%s expects field enclosure to be a single character. %s given.', __METHOD__, $strEnclosure));
        }

        // If the CSV document was created or is read on a Macintosh computer,
        // add the following lines before using the library to help PHP detect line ending in Mac OS X.
        if (!\ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        // Get the League\Csv\Reader object
        $objCsvReader = Reader::createFromPath($this->projectDir.'/'.$objCsvFile->path, 'r');

        // Set the CSV header offset
        $objCsvReader->setHeaderOffset(0);

        // Set the delimiter string
        $objCsvReader->setDelimiter($strDelimiter);

        // Set enclosure string
        $objCsvReader->setEnclosure($strEnclosure);

        // Get the primary key
        $strPrimaryKey = $this->getPrimaryKey($tableName);

        if (null === $strPrimaryKey) {
            throw new \Exception('No primary key found in '.$tableName);
        }

        // Load language file
        $this->system->loadLanguageFile($tableName);

        // Store the options in $this->arrData
        $this->arrData = [
            'taskId' => $taskId,
            'objCsvFile' => $objCsvFile,
            'tableName' => $tableName,
            'primaryKey' => $strPrimaryKey,
            'importMode' => $strImportMode,
            'selectedFields' => $arrSelectedFields,
            'strDelimiter' => $strDelimiter,
            'strEnclosure' => $strEnclosure,
            'strArrayDelimiter' => $strArrayDelimiter,
            'blnTestMode' => $blnTestMode,
            'arrSkipValidationFields' => $arrSkipValidationFields,
            'intOffset' => $intOffset,
            'intLimit' => $intLimit,
        ];

        // Truncate table
        if ('truncate_table' === $this->arrData['importMode'] && false === $blnTestMode) {
            $this->connection->executeStatement('TRUNCATE TABLE '.$this->arrData['tableName']);
        }

        if (\count($this->arrData['selectedFields']) < 1) {
            return;
        }

        // Get Line (Header is line 0)
        $this->currentLine = $intOffset;

        // Get the League\Csv\Statement object
        $stmt = new Statement();

        // Set offset
        if ($intOffset > 0) {
            $stmt = $stmt->offset($intOffset);
        }

        // Set limit
        if ($intLimit > 0) {
            $stmt = $stmt->limit($intLimit);
        }

        // Get each line as an associative array -> ['columnName1' => 'value1',  'columnName2' => 'value2']
        $arrRecords = $stmt->process($objCsvReader);

        // Process each row and try to write the values to the database
        foreach ($arrRecords as $arrRecord) {
            $doNotSave = false;

            $this->resetInsertException();

            // Update current line (CSV)
            ++$this->currentLine;

            // Update processed rows counter
            ++$this->countProcessedRows;

            $set = [];
            $arrReportValues = [];

            foreach ($arrRecord as $columnName => $varValue) {
                $varValue = trim($varValue);

                // Do not process empty values
                if (!\strlen($varValue)) {
                    continue;
                }

                // Continue if field is excluded from import
                if (!\in_array($columnName, $this->arrData['selectedFields'], true)) {
                    continue;
                }

                // Auto increment if data records are appended
                if ('append_entries' === $this->arrData['importMode'] && strtolower($columnName) === strtolower($this->arrData['primaryKey'])) {
                    continue;
                }

                // Get the DCA of the current field
                $arrDca = $this->getDca($columnName, $tableName);

                // Map checkboxWizards to regular checkbox widgets
                if ('checkboxWizard' === $arrDca['inputType']) {
                    $arrDca['inputType'] = 'checkbox';
                }

                // Set the correct date format
                $varValue = $this->formatter->getCorrectDateFormat($varValue, $arrDca);

                // Convert strings to array
                $varValue = $this->formatter->convertToArray($varValue, $arrDca, $this->arrData['strArrayDelimiter']);

                // Set $_POST, so the content can be validated
                $this->input->setPost($columnName, $varValue);

                // Get the right widget for input validation, etc.
                $objWidget = $this->getWidgetFromDca($arrDca, $columnName, $this->arrData['tableName'], $varValue);

                // Trigger the importFromCsv HOOK:
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && \is_array($GLOBALS['TL_HOOKS']['importFromCsv'])) {
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback) {
                        $this->system->importStatic($callback[0])->{$callback[1]}($objWidget, $arrRecord, $this->currentLine, $this);
                    }
                }

                // Validate date, datim or time values
                $this->importValidator->checkIsValidDate($objWidget, $arrDca);

                // Special treatment for password
                if ('password' === $arrDca['inputType']) {
                    $this->input->setPost('password_confirm', $objWidget->value);
                }

                // Skip validation for selected fields
                if (!\in_array($objWidget->strField, $this->arrData['arrSkipValidationFields'], true)) {
                    // Validate input
                    $objWidget->validate();
                }

                $this->importValidator->checkIsUnique($objWidget, $arrDca);

                // Add value to the report window
                $arrReportValues[$objWidget->strField] = $objWidget->value;

                if (\is_array($objWidget->value)) {
                    $arrReportValues[$objWidget->strField] = print_r($objWidget->value, true);
                }

                $objWidget->value = $this->formatter->convertDateToTimestamp($objWidget, $arrDca);
                $objWidget->value = $this->formatter->replaceNewlineTags($objWidget->value);

                if ($objWidget->hasErrors()) {
                    $doNotSave = true;
                    $arrReportValues[$objWidget->strField] = sprintf(
                        '"%s" => %s',
                        $objWidget->value,
                        $objWidget->getErrorsAsString(' '),
                    );
                } else {
                    $set[$objWidget->strField] = \is_array($objWidget->value) ? serialize($objWidget->value) : $objWidget->value;
                }
            } // End foreach column

            if (!$doNotSave) {
                // Auto insert "tstamp"
                if ($this->columnExists('tstamp', $this->arrData['tableName'])) {
                    if (!isset($set['tstamp']) || '' === $set['tstamp']) {
                        $set['tstamp'] = time();
                        $arrReportValues['tstamp'] = time();
                    }
                }

                // Auto insert "dateAdded"
                if ($this->columnExists('dateAdded', $this->arrData['tableName'])) {
                    if (!isset($set['dateAdded']) || '' === $set['dateAdded']) {
                        $set['dateAdded'] = time();
                        $arrReportValues['dateAdded'] = date($this->config->get('dateFormat'), time());
                    }
                }

                // Write the data record to the database
                if (true !== $this->arrData['blnTestMode']) {
                    $insertId = null;

                    try {
                        $this->connection->beginTransaction();
                        $this->connection->insert($this->arrData['tableName'], $set);
                        $this->connection->commit();
                        $insertId = $this->connection->lastInsertId();
                    } catch (\Exception $e) {
                        $doNotSave = true;
                        $this->insertException = $e;
                        $this->connection->rollBack();
                    }

                    // Dispatch import_from_csv.post_import event (Add newsletter recipients, ...)
                    if ($insertId) {
                        $event = new PostImportEvent($tableName, $set, $insertId, $this);
                        $this->eventDispatcher->dispatch($event, PostImportEvent::NAME);
                    }
                }
            }

            // Collect data for the logger screen in the Contao backend
            // The logger service requires a running session.
            // Do not run the logger if there is no request (e.g. cron jobs)
            if ($this->importLogger->hasInitialized($taskId)) {
                $arrLog = [];
                $arrLog['line'] = $this->currentLine;

                if ($doNotSave) {
                    $arrLog['type'] = 'failure';
                    $arrLog['text'] = $this->hasInsertException() ? $this->getInsertExceptionAsString() : '';

                    // Increment the error counter
                    ++$this->insertErrors;
                } else {
                    $arrLog['type'] = 'success';
                    $arrLog['text'] = '';
                }

                $arrLog['values'] = [];

                foreach ($arrReportValues as $k => $v) {
                    if (\is_array($v)) {
                        $v = serialize($v);
                    }

                    $arrLog['values'][] = [
                        'column' => $k,
                        'value' => (string) $v,
                    ];
                }

                if ('failure' === $arrLog['type']) {
                    $this->importLogger->addFailure($this->getData('taskId'), $arrLog['line'], $arrLog['text'], $arrLog['values']);
                } else {
                    $this->importLogger->addSuccess($this->getData('taskId'), $arrLog['line'], $arrLog['text'], $arrLog['values']);
                }
            }
        }// End for each data record

        if ($this->importLogger->hasInitialized($taskId)) {
            $this->importLogger->setSummaryData($this->getData('taskId'), $this->countProcessedRows, $this->countProcessedRows - $this->insertErrors, $this->insertErrors);
        }
    }

    public function getData(string $key)
    {
        return $this->arrData[$key] ?? null;
    }

    public function setData(string $key, $varValue): void
    {
        $this->arrData[$key] = $varValue;
    }

    public function getCurrentLine(): int
    {
        return $this->currentLine;
    }

    public function getNumberOfInsertErrors(): int
    {
        return $this->insertErrors;
    }

    public function getNumberOfProcessedRows(): int
    {
        return $this->countProcessedRows;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getPrimaryKey(string $tableName): ?string
    {
        $stmt = $this->connection->executeQuery('SHOW INDEX FROM '.$tableName." WHERE Key_name = 'PRIMARY'");

        while (($row = $stmt->fetchAssociative()) !== false) {
            if (!empty($row['Column_name'])) {
                return $row['Column_name'];
            }
        }

        return null;
    }

    public function getDca(string $columnName, string $tableName): array
    {
        $this->controller->loadDataContainer($tableName);

        if (\is_array($GLOBALS['TL_DCA'][$tableName]['fields'][$columnName])) {
            $arrDca = &$GLOBALS['TL_DCA'][$tableName]['fields'][$columnName];

            if (isset($arrDca['inputType']) && \is_string($arrDca['inputType'])) {
                return $arrDca;
            }

            $arrDca['inputType'] = 'text';
        }

        return [
            'inputType' => 'text',
        ];
    }

    public function getWidgetFromDca(array $arrDca, string $columnName, string $tableName, $varValue): Widget
    {
        $inputType = $arrDca['inputType'] ?? '';
        $request = $this->requestStack->getCurrentRequest();

        $objDca = $request ? new DC_Table($tableName) : null;

        $strClass = $GLOBALS['BE_FFL'][$inputType] ?? '';

        if (!empty($strClass) && class_exists($strClass)) {
            return new $strClass($strClass::getAttributesFromDca($arrDca, $columnName, $varValue, $columnName, $tableName, $objDca));
        }

        $strClass = $GLOBALS['BE_FFL']['text'];

        return new $strClass($strClass::getAttributesFromDca($arrDca, $columnName, $varValue, $columnName, $tableName, $objDca));
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function columnExists(string $columnName, string $tableName): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns($tableName);

        return isset($columns[strtolower($columnName)]);
    }

    private function resetInsertException(): void
    {
        $this->insertException = null;
    }

    private function hasInsertException(): bool
    {
        return null !== $this->insertException;
    }

    private function getInsertExceptionAsString(): ?string
    {
        return $this->insertException?->getMessage();
    }
}
