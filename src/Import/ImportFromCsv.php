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
use Contao\File;
use Contao\Input;
use Contao\System;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\SyntaxError;
use League\Csv\UnavailableStream;
use Markocupic\ImportFromCsvBundle\Event\ConfigImportEvent;
use Markocupic\ImportFromCsvBundle\Event\PostImportBatchEvent;
use Markocupic\ImportFromCsvBundle\Event\PostImportRowEvent;
use Markocupic\ImportFromCsvBundle\Event\PreImportRowEvent;
use Markocupic\ImportFromCsvBundle\Event\PreValidateWidgetEvent;
use Markocupic\ImportFromCsvBundle\Import\Field\Formatter;
use Markocupic\ImportFromCsvBundle\Import\Field\ImportValidator;
use Markocupic\ImportFromCsvBundle\Logger\ImportLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly WidgetFactory $widgetFactory,
        private readonly ImportPersister $importPersister,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws UnavailableStream
     * @throws \Doctrine\DBAL\Exception
     */
    public function importCsv(File $csvFile, string $tableName, string $importMode, array $selectedFields = [], array $mapValues = [], string $delimiter = ';', string $enclosure = '"', string $arrayDelimiter = '||', bool $isTestMode = false, array $skipValidationFields = [], int $offset = 0, int $limit = 0, string|null $taskId = null, string|null $matchBy = null): void
    {
        // Generate a task id if there is none.
        $taskId = $taskId ?? uniqid();

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \Exception('No request found.');
        }

        if (!$this->importLogger->hasInitialized($taskId)) {
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
        /** @var Reader $reader */
        $reader = $this->framework
            ->getAdapter(Reader::class)
            ->from($csvFile->getRealPath(), 'r')
        ;

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

        $selectedFields = $this->normalizeSelectedFields($selectedFields);

        // Store the options in $this->config
        $config = new ImportConfig(
            taskId: $taskId,
            csvFile: $csvFile,
            tableName: $tableName,
            primaryKey: $primaryKey,
            importMode: $importMode,
            matchBy: $matchBy,
            selectedFields: $selectedFields,
            mapValues: $mapValues,
            delimiter: $delimiter,
            enclosure: $enclosure,
            arrayDelimiter: $arrayDelimiter,
            isTestMode: $isTestMode,
            skipValidationFields: $skipValidationFields,
            offset: $offset,
            limit: $limit,
        );

        $configImportEvent = new ConfigImportEvent($tableName, $config, $this);
        $this->eventDispatcher->dispatch($configImportEvent);

        $this->config = $configImportEvent->getConfig();

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

        $headerFields = $this->mapHeaderNames(
            $reader->getHeader(),
            $this->config->selectedFields,
        );

        // Get each line as an associative array -> ['columnName1' => 'value1',
        // 'columnName2' => 'value2']
        $csvLines = $stmt->process($reader, $headerFields);

        $existingPrimaryKeys = $this->findExistingPrimaryKeysByMatchField(
            $csvLines,
            $this->config->tableName,
            $this->config->primaryKey,
            $this->config->matchBy,
        );

        $importData = [];
        // Process each row and filter/skip empty or not allowed values/columns
        $doNotSave = false;

        $arrReportValues = [];

        foreach ($csvLines as $csvLine) {
            $csvRecord = [];

            foreach ($csvLine as $columnName => $value) {
                $value = trim((string) $value);

                // Do not process empty values
                if (!\strlen($value)) {
                    continue;
                }

                // Continue if field is excluded from import
                if (empty($this->config->selectedFields[$columnName])) {
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

            foreach ($csvRecord as $columnName => $value) {
                // Get the DCA of the current field
                $dca = $this->getDca($columnName, $this->config->tableName);

                // Map checkboxWizards to regular checkbox widgets
                if ('checkboxWizard' === $dca['inputType']) {
                    $dca['inputType'] = 'checkbox';
                }

                $value = $this->mapValues($columnName, $value);

                // Set the correct date format
                $value = $this->formatter->getCorrectDateFormat($value, $dca);

                // Convert strings to array
                $value = $this->formatter->convertToArray($value, $dca, $this->config->arrayDelimiter);

                // Input::setPost($value), so the content can be validated
                $this->framework->getAdapter(Input::class)->setPost($columnName, $value);

                // Widget::getPost() takes the (input encoded) value from current request
                $request->request->set($columnName, $value);

                // Get the correct widget for input validation, etc.
                $widget = $this->widgetFactory->create(dca: $dca, columnName: $columnName, tableName: $this->config->tableName, value: $value);

                $preValidateWidgetEvent = new PreValidateWidgetEvent($widget, $csvRecord, $this, $request);
                $this->eventDispatcher->dispatch($preValidateWidgetEvent);

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
                $arrReportValues[$this->currentLine][$widget->strField] = $widget->value;

                if (\is_array($widget->value)) {
                    $arrReportValues[$this->currentLine][$widget->strField] = print_r($widget->value, true);
                }

                $widget->value = $this->formatter->strtotime($widget, $dca);
                $widget->value = $this->formatter->replaceNewlineTags($widget->value);

                if (
                    $widget->strField !== $this->config->matchBy
                    && $widget->hasErrors()
                ) {
                    $doNotSave = true;
                    $arrReportValues[$this->currentLine][$widget->strField] = \sprintf(
                        '"%s" => %s',
                        $widget->value,
                        $widget->getErrorsAsString(' '),
                    );
                } else {
                    $set[$widget->strField] = \is_array($widget->value) ? serialize($widget->value) : $widget->value;
                }
            } // End foreach column

            if (!empty($existingPrimaryKeys[$set[$this->config->matchBy]])) {
                $set[$this->config->primaryKey] = $existingPrimaryKeys[$set[$this->config->matchBy]];
            }

            // Auto-insert "tstamp"
            if ($this->columnExists('tstamp', $this->config->tableName)) {
                if (!isset($set['tstamp']) || '' === $set['tstamp']) {
                    $set['tstamp'] = time();
                    $arrReportValues[$this->currentLine]['tstamp'] = time();
                }
            }

            // Auto-insert "dateAdded"
            if ($this->columnExists('dateAdded', $this->config->tableName)) {
                if (!isset($set['dateAdded']) || '' === $set['dateAdded']) {
                    $set['dateAdded'] = time();
                    $arrReportValues[$this->currentLine]['dateAdded'] = Date::parse($this->framework->getAdapter(Config::class)->get('dateFormat'), time());
                }
            }

            $importData[$this->currentLine] = $set;
        }// End for each data record

        foreach ($importData as $currentLine => $set) {
            if ($doNotSave) {
                continue;
            }

            $this->connection->beginTransaction();

            try {
                $preImportRowEvent = new PreImportRowEvent($this->config->tableName, $set, $csvRecord, $this);
                $this->eventDispatcher->dispatch($preImportRowEvent);

                $id = $this->importPersister->upsert(
                    $this->config->tableName,
                    $this->config->primaryKey,
                    $preImportRowEvent->getDataRecord(),
                );

                if (true !== $this->config->isTestMode) {
                    $this->connection->commit();
                } else {
                    $this->connection->rollBack();
                }

                $postImportRowEvent = new PostImportRowEvent($this->config->tableName, $set, $id, $csvRecord, $this);
                $this->eventDispatcher->dispatch($postImportRowEvent);
            } catch (\Throwable $e) {
                $doNotSave = true;
                $this->addInsertException($e);
                $this->connection->rollBack();
            }

            // Collect data for the logger screen in the Contao backend The logger service
            // requires a running session. Do not run the logger if there is no request (e.g.
            // cron jobs)
            if ($this->importLogger->hasInitialized($taskId)) {
                $arrLog = [];
                $arrLog['line'] = $currentLine;

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

                foreach ($arrReportValues[$currentLine] as $k => $v) {
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
        }

        if ($this->importLogger->hasInitialized($taskId)) {
            $this->importLogger->setSummaryData($this->config->taskId, $this->countProcessedRows, $this->countProcessedRows - $this->insertErrors, $this->insertErrors);
        }

        $event = new PostImportBatchEvent($this, $request, $importData);
        $this->eventDispatcher->dispatch($event);
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

    public function getLogger(): ImportLogger
    {
        return $this->importLogger;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findPrimaryKey(string $tableName): string|null
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

        if (!isset($GLOBALS['TL_DCA'][$tableName]['fields'][$columnName])) {
            return [
                'inputType' => 'text',
            ];
        }
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

    public function addInsertException(\Throwable $e): void
    {
        $this->insertExceptions[] = $e;
    }

    /**
     * Finds existing database records by comparing CSV match-field values against the given table.
     *
     * The function extracts all non-empty values of the `$matchBy` field from the CSV lines,
     * performs a lookup in the database table, and returns a map where each match-field value
     * is mapped to the corresponding primary key of an existing record.
     *
     * This allows the import pipeline to decide whether a CSV row should update an existing
     * record (match found) or create a new one (no match found).
     *
     * Example return value:
     *
     * [
     *     'marko@example.com' => 42,
     *     'sascha@example.com' => 17,
     * ]
     *
     * @return array<string|int, int> map of match-field values to existing primary keys
     */
    private function findExistingPrimaryKeysByMatchField(iterable $csvLines, string $tableName, string $primaryKey, string|null $matchBy): array
    {
        if (null === $matchBy) {
            return [];
        }

        $matchFieldValues = [];

        foreach ($csvLines as $line) {
            $value = $line[$matchBy] ?? null;

            if (null === $value || '' === $value) {
                continue;
            }

            $matchFieldValues[] = $value;
        }

        if ([] === $matchFieldValues) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select("t.$matchBy", "t.$primaryKey")
            ->from($tableName, 't')
            ->where($qb->expr()->in("t.$matchBy", ':matchBy'))
            ->setParameter(
                'matchBy',
                $matchFieldValues,
                $this->inferArrayParameterType($tableName, $matchBy),
            )
        ;

        return $qb->fetchAllKeyValue();
    }

    private function normalizeSelectedFields(array $selectedFields): array
    {
        $normalizedSelectedFields = [];

        foreach ($selectedFields as $field) {
            $normalizedSelectedFields[$field['field_name'] ?? $field['csv_field_name']] = $field['csv_field_name'];
        }

        return $normalizedSelectedFields;
    }

    private function mapHeaderNames(array $csvHeaderFields, array $selectedFields): array
    {
        foreach ($selectedFields as $tableFieldName => $csvFieldName) {
            $headerFieldIndex = array_search($csvFieldName, $csvHeaderFields, true);
            $csvHeaderFields[$headerFieldIndex] = $tableFieldName;
        }

        return $csvHeaderFields;
    }

    /**
     * @throws Exception
     */
    private function inferArrayParameterType(string $tableName, string $column): ArrayParameterType
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns($tableName);

        if (!isset($columns[$column])) {
            throw new \InvalidArgumentException(\sprintf('Unknown column "%s" on table "%s".', $column, $tableName));
        }

        $type = $columns[$column]->getType(); // Doctrine\DBAL\Types\Type instance

        // Works across DBAL versions: resolve the registered type name from the TypeRegistry.
        $typeName = $this->framework->getAdapter(Type::class)->getTypeRegistry()->lookupName($type);

        return match ($typeName) {
            'integer', 'bigint', 'smallint' => ArrayParameterType::INTEGER,
            default => ArrayParameterType::STRING,
        };
    }

    private function mapValues(string $columnName, mixed $value): string
    {
        if (empty($this->config->mapValues) || empty($value)) {
            return $value;
        }

        foreach ($this->config->mapValues as $mapping) {
            if ($mapping['field_name'] !== $columnName) {
                continue;
            }

            if ($mapping['lowercase']) {
                $value = strtolower($value);
            }

            if ($mapping['uppercase']) {
                $value = strtoupper($value);
            }

            if (empty($mapping['csv_field_value']) || strtolower($mapping['csv_field_value']) !== strtolower($value)) {
                continue;
            }

            return $mapping['transform_to'];
        }

        return $value;
    }
}
