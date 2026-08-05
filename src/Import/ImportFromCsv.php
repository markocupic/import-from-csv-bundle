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

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
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
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportFromCsv
{
    private ImportConfig|null $config;

    private array $insertExceptions = [];

    private int $countProcessedRows = 0;

    private int $currentLine = 0;

    private int $insertErrors = 0;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Formatter $formatter,
        private readonly ImportLogger $importLogger,
        private readonly ImportValidator $importValidator,
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
        private readonly WidgetFactory $widgetFactory,
        private readonly BatchInserter $batchInserter,
    ) {
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws UnavailableStream
     * @throws \Doctrine\DBAL\Exception
     */
    public function importCsv(File $csvFile, string $tableName, string $importMode, array $selectedFields = [], string $delimiter = ';', string $enclosure = '"', string $arrayDelimiter = '||', bool $isTestMode = false, array $skipValidationFields = [], int $offset = 0, int $limit = 0, string|null $taskId = null): void
    {
        // Generate a task id if there is none.
        $taskId = $taskId ?? uniqid();

        $request = $this->requestStack->getCurrentRequest();

        if (!$this->importLogger->hasInitialized($taskId) && $request) {
            $taskId = $this->importLogger->initialize($taskId);
        }

        $this->framework->getAdapter(Controller::class)->loadLanguageFile('tl_import_from_csv');

        $csvFile = new \SplFileInfo(Path::join($this->projectDir, $csvFile->path));
        $delimiter = '' === $delimiter ? ';' : $delimiter;
        $enclosure = '' === $enclosure ? '"' : $enclosure;
        $arrayDelimiter = '' === $arrayDelimiter ? '||' : $arrayDelimiter;

        // Throw an exception if the submitted string length is not equal to 1 byte.
        if (\strlen($delimiter) > 1) {
            throw new \Exception(\sprintf('%s expects field delimiter to be a single character. %s given.', __METHOD__, $delimiter));
        }

        // Throw an exception if the submitted string length is not equal to 1 byte.
        if (\strlen($enclosure) > 1) {
            throw new \Exception(\sprintf('%s expects field enclosure to be a single character. %s given.', __METHOD__, $enclosure));
        }

        // If the CSV document was created or is read on a Macintosh computer, add the
        // following lines before using the library to help PHP detect line ending in Mac
        // OS X.
        if (!\ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        // Get the League\Csv\Reader object
        $reader = Reader::createFromPath($csvFile->getRealPath(), 'r');

        // Set the CSV header offset
        $reader->setHeaderOffset(0);

        // Set the delimiter string
        $reader->setDelimiter($delimiter);

        // Set enclosure string
        $reader->setEnclosure($enclosure);

        // Get the primary key
        $primaryKey = $this->findPrimaryKey($tableName);

        if (null === $primaryKey) {
            throw new \Exception('No primary key found in '.$tableName);
        }

        // Load language file
        $this->framework->getAdapter(System::class)->loadLanguageFile($tableName);

        // Store the options in $this->config
        $this->config = new ImportConfig(
            taskId: $taskId,
            csvFile: $csvFile,
            tableName: $tableName,
            primaryKey: $primaryKey,
            importMode: $importMode,
            selectedFields: $selectedFields,
            delimiter: $delimiter,
            enclosure: $enclosure,
            arrayDelimiter: $arrayDelimiter,
            isTestMode: $isTestMode,
            skipValidationFields: $skipValidationFields,
            offset: $offset,
            limit: $limit,
        );

        // Truncate table
        if ('truncate_table' === $this->config->importMode && false === $this->config->isTestMode) {
            $this->connection->executeStatement('TRUNCATE TABLE '.$this->config->tableName);
        }

        if (\count($this->config->selectedFields) < 1) {
            return;
        }

        // Get Line (Header is line 0)
        $this->currentLine = $this->config->offset;

        // Get the League\Csv\Statement object
        $stmt = new Statement();

        // Set offset
        if ($this->config->offset > 0) {
            $stmt = $stmt->offset($this->config->offset);
        }

        // Set limit
        if ($this->config->limit > 0) {
            $stmt = $stmt->limit($this->config->limit);
        }

        // Get each line as an associative array -> ['columnName1' => 'value1',
        // 'columnName2' => 'value2']
        $csvLines = $stmt->process($reader);

        // Process each row and filter/skip empty or not allowed values/columns

        foreach ($csvLines as $csvLine) {
            $doNotSave = false;

            $csvRecord = [];

            foreach ($csvLine as $columnName => $value) {
                $value = trim((string) $value);

                // Do not process empty values
                if (!\strlen($value)) {
                    continue;
                }

                // Continue if field is excluded from import
                if (!\in_array($columnName, $this->config->selectedFields, true)) {
                    continue;
                }

                // Auto increment if data records are appended
                if ('append_entries' === $this->config->importMode && strtolower($columnName) === strtolower($this->config->primaryKey)) {
                    continue;
                }

                $csvRecord[$columnName] = $value;
            }

            $this->resetInsertExceptions();

            // Update current line (CSV)
            ++$this->currentLine;

            // Update processed rows counter
            ++$this->countProcessedRows;

            $set = [];
            $arrReportValues = [];

            foreach ($csvRecord as $columnName => $value) {
                // Get the DCA of the current field
                $dca = $this->getDca($columnName, $tableName);

                // Map checkboxWizards to regular checkbox widgets
                if ('checkboxWizard' === $dca['inputType']) {
                    $dca['inputType'] = 'checkbox';
                }

                // Set the correct date format
                $value = $this->formatter->getCorrectDateFormat($value, $dca);

                // Convert strings to array
                $value = $this->formatter->convertToArray($value, $dca, $this->config->arrayDelimiter);

                // Input::setPost($value), so the content can be validated
                $this->framework->getAdapter(Input::class)->setPost($columnName, $value);

                // Widget::getPost() takes the (input encoded) value from current request
                $request->request->set($columnName, $value);

                // Get the correct widget for input validation, etc.
                // $widget = $this->getWidgetFromDca($dca, $columnName, $this->config->tableName, $value);
                $widget = $this->widgetFactory->createWidget($dca, $columnName, $this->config->tableName, $value);

                // Trigger the importFromCsv HOOK:
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && \is_array($GLOBALS['TL_HOOKS']['importFromCsv'])) {
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback) {
                        $this->framework->getAdapter(System::class)->importStatic($callback[0])->{$callback[1]}($widget, $csvRecord, $this->currentLine, $this);
                    }
                }

                // Validate date, datim or time values
                $this->importValidator->checkIsValidDate($widget, $dca);

                // Special treatment for password
                if ('password' === $dca['inputType']) {
                    $this->framework->getAdapter(Input::class)->setPost('password_confirm', $widget->value);
                    // Later we will use a post-insert listener to set the correct password with the
                    // correct password hasher.
                }

                // Skip validation for selected fields
                if (!\in_array($widget->strField, $this->config->skipValidationFields, true)) {
                    // Validate input
                    $widget->validate();
                }

                $this->importValidator->checkIsUnique($widget, $dca);

                // Add value to the report window
                $arrReportValues[$widget->strField] = $widget->value;

                if (\is_array($widget->value)) {
                    $arrReportValues[$widget->strField] = print_r($widget->value, true);
                }

                $widget->value = $this->formatter->strtotime($widget, $dca);
                $widget->value = $this->formatter->replaceNewlineTags($widget->value);

                if ($widget->hasErrors()) {
                    $doNotSave = true;
                    $arrReportValues[$widget->strField] = \sprintf(
                        '"%s" => %s',
                        $widget->value,
                        $widget->getErrorsAsString(' '),
                    );
                } elseif (empty($widget->skipImport)) {
                    $set[$widget->strField] = \is_array($widget->value) ? serialize($widget->value) : $widget->value;
                }
            } // End foreach column

            if (!$doNotSave) {
                // Auto-insert "tstamp"
                if ($this->columnExists('tstamp', $this->config->tableName)) {
                    if (!isset($set['tstamp']) || '' === $set['tstamp']) {
                        $set['tstamp'] = time();
                        $arrReportValues['tstamp'] = time();
                    }
                }

                // Auto-insert "dateAdded"
                if ($this->columnExists('dateAdded', $this->config->tableName)) {
                    if (!isset($set['dateAdded']) || '' === $set['dateAdded']) {
                        $set['dateAdded'] = time();
                        $arrReportValues['dateAdded'] = Date::parse($this->framework->getAdapter(Config::class)->get('dateFormat'), time());
                    }
                }

                // Write the data record to the database
                if (true !== $this->config->isTestMode) {
                    $insertId = null;

                    try {
                        $insertId = $this->batchInserter->insertRow(
                            $this->config->tableName,
                            $set,
                            $csvRecord,
                            $this,
                            true, // dispatchPostImportEvent
                        );

                        $this->batchInserter->commitIfBatchBoundary($this->countProcessedRows);
                    } catch (\Exception $e) {
                        $doNotSave = true;
                        $this->addInsertException($e);
                        $this->batchInserter->rollbackIfActive();
                    }

                    // Dispatch the import_from_csv.post_import event (Add newsletter recipients, ...)
                    if ($insertId) {
                        $event = new PostImportEvent($tableName, $set, $insertId, $csvRecord, $this);
                        $this->eventDispatcher->dispatch($event, PostImportEvent::NAME);
                    }
                }
            }

            $this->batchInserter->commitRemainder();

            // Collect data for the logger screen in the Contao backend The logger service
            // requires a running session. Do not run the logger if there is no request (e.g.
            // cron jobs)
            if ($this->importLogger->hasInitialized($taskId)) {
                $arrLog = [];
                $arrLog['line'] = $this->currentLine;

                if ($doNotSave) {
                    $arrLog['type'] = 'failure';
                    $arrLog['text'] = $this->getInsertExceptionsAsString();

                    // Increment the error counter
                    ++$this->insertErrors;
                } elseif ($this->hasInsertExceptions()) {
                    // If an exception has been thrown in a post-insert listener...
                    $arrLog['type'] = 'failure';
                    $arrLog['text'] = $this->getInsertExceptionsAsString();

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
                    $this->importLogger->addFailure($this->config->taskId, $arrLog['line'], $arrLog['text'], $arrLog['values']);
                } else {
                    $this->importLogger->addSuccess($this->config->taskId, $arrLog['line'], $arrLog['text'], $arrLog['values']);
                }
            }
        }// End for each data record

        if ($this->importLogger->hasInitialized($taskId)) {
            $this->importLogger->setSummaryData($this->config->taskId, $this->countProcessedRows, $this->countProcessedRows - $this->insertErrors, $this->insertErrors);
        }
    }

    public function getConfig(): ImportConfig|null
    {
        return $this->config;
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
    public function findPrimaryKey(string $tableName): ?string
    {
        $stmt = $this->connection->executeQuery("SHOW INDEX FROM $tableName WHERE Key_name = 'PRIMARY'");

        while (($row = $stmt->fetchAssociative()) !== false) {
            if (!empty($row['Column_name'])) {
                return $row['Column_name'];
            }
        }

        return null;
    }

    public function getDca(string $columnName, string $tableName): array
    {
        $this->framework->getAdapter(Controller::class)->loadDataContainer($tableName);

        if (\is_array($GLOBALS['TL_DCA'][$tableName]['fields'][$columnName])) {
            $dca = &$GLOBALS['TL_DCA'][$tableName]['fields'][$columnName];

            if (isset($dca['inputType']) && \is_string($dca['inputType'])) {
                return $dca;
            }

            $dca['inputType'] = 'text';
        }

        return [
            'inputType' => 'text',
        ];
    }

    public function getWidgetFromDca(array $dca, string $columnName, string $tableName, $value): Widget
    {
        $inputType = $dca['inputType'] ?? '';
        $request = $this->requestStack->getCurrentRequest();

        $objDca = $request ? new DC_Table($tableName) : null;

        $strClass = $GLOBALS['BE_FFL'][$inputType] ?? '';

        if (!empty($strClass) && class_exists($strClass)) {
            return new $strClass($strClass::getAttributesFromDca($dca, $columnName, $value, $columnName, $tableName, $objDca));
        }

        $strClass = $GLOBALS['BE_FFL']['text'];

        return new $strClass($strClass::getAttributesFromDca($dca, $columnName, $value, $columnName, $tableName, $objDca));
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

    public function resetInsertExceptions(): void
    {
        $this->insertExceptions = [];
    }

    public function hasInsertExceptions(): bool
    {
        return !empty($this->insertExceptions);
    }

    public function getInsertExceptionsAsString(): string
    {
        if ($this->hasInsertExceptions()) {
            return implode(' ', array_map(static fn ($e) => $e->getMessage(), $this->insertExceptions));
        }

        return '';
    }

    public function addInsertException(\Exception $e): void
    {
        $this->insertExceptions[] = $e;
    }

    private function quoteKeys(array $csvRecord): array
    {
        $quotedRecord = [];

        foreach ($csvRecord as $k => $v) {
            $quotedRecord[$this->connection->quoteIdentifier($k)] = $v;
        }

        return $quotedRecord;
    }
}
