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

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\File;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use Markocupic\ImportFromCsvBundle\Import\Field\Formatter;
use Markocupic\ImportFromCsvBundle\Import\Field\Validator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportFromCsv
{
    private ContaoFramework $framework;
    private Connection $connection;
    private TranslatorInterface $translator;
    private Formatter $formatter;
    private Validator $validator;
    private RequestStack $requestStack;
    private string $projectDir;
    private array $arrData = [];

    public function __construct(ContaoFramework $framework, Connection $connection, TranslatorInterface $translator, Formatter $formatter, Validator $validator, RequestStack $requestStack, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->formatter = $formatter;
        $this->validator = $validator;
        $this->requestStack = $requestStack;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws \Exception
     * @throws Exception
     * @throws InvalidArgument
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function importCsv(File $objCsvFile, string $tableName, string $strImportMode, array $arrSelectedFields = [], string $strDelimiter = ';', string $strEnclosure = '"', string $strArrayDelimiter = '||', bool $blnTestMode = false, array $arrSkipValidationFields = [], int $intOffset = 0, int $intLimit = 0): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $controllerAdapter->loadLanguageFile('tl_import_from_csv');

        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag('markocupic_import_from_csv');

        $data['rows'] = '';
        $data['summary'] = [
            'rows' => 0,
            'success' => 0,
            'errors' => 0,
        ];

        $bag->replace($data);

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
        $systemAdapter->loadLanguageFile($tableName);

        // Store the options in $this->arrData
        $this->arrData = [
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

        // Count inserts (depends on offset and limit and is not equal to $row)
        $countInserts = 0;

        // Count errors
        $insertError = 0;

        // Get Line (Header is line 0)
        $line = $intOffset;

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

        // Get each line as an associative array -> array('columnName1' => 'value1',  'columnName2' => 'value2')
        // and store each record in the db
        $arrRecords = $stmt->process($objCsvReader);

        foreach ($arrRecords as $arrRecord) {
            $doNotSave = false;

            // Count lines
            ++$line;

            // Count inserts
            ++$countInserts;

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
                $request = $this->requestStack->getCurrentRequest();
                $request->request->set($columnName, $varValue);
                $inputAdapter->setPost($columnName, $varValue);

                // Get the right widget for input validation, etc.
                $objWidget = $this->getWidgetFromDca($arrDca, $columnName, $this->arrData['tableName'], $varValue);

                // Trigger the importFromCsv HOOK:
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && \is_array($GLOBALS['TL_HOOKS']['importFromCsv'])) {
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback) {
                        $systemAdapter->importStatic($callback[0])->{$callback[1]}($objWidget, $arrRecord, $line, $this);
                    }
                }

                // Validate date, datim or time values
                $this->validator->checkIsValidDate($objWidget, $arrDca);

                // Special treatment for password
                if ('password' === $arrDca['inputType']) {
                    $inputAdapter->setPost('password_confirm', $objWidget->value);
                }

                // Skip validation for selected fields
                if (!\in_array($objWidget->strField, $this->arrData['arrSkipValidationFields'], true)) {
                    // Validate input
                    $objWidget->validate();
                }

                $this->validator->checkIsUnique($objWidget, $arrDca);

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
                        '"%s" => <span class="ifcb-error-msg">%s</span>',
                        $objWidget->value,
                        $objWidget->getErrorsAsString(' '),
                    );
                } else {
                    $set[$objWidget->strField] = \is_array($objWidget->value) ? serialize($objWidget->value) : $objWidget->value;
                }
            }
            // End foreach

            // Insert data record
            if (!$doNotSave) {
                // Auto insert "tstamp"
                if ($this->hasColumn('tstamp', $this->arrData['tableName'])) {
                    if (!isset($set['tstamp']) || '' === $set['tstamp']) {
                        $set['tstamp'] = time();
                        $arrReportValues['tstamp'] = time();
                    }
                }

                // Auto insert "dateAdded"
                if ($this->hasColumn('dateAdded', $this->arrData['tableName'])) {
                    if (!isset($set['dateAdded']) || '' === $set['dateAdded']) {
                        $set['dateAdded'] = time();
                        $arrReportValues['dateAdded'] = date($configAdapter->get('dateFormat'), time());
                    }
                }

                // Add to newsletter
                if (($set['newsletter'] ?? null) && '' !== $set['newsletter'] && ($set['email'] ?? null) && '' !== $set['email']) {
                    $this->addNewMemberToNewsletterRecipientList($this->arrData['tableName'], $set['newsletter'], $set['email']);
                }

                // Add new record to the database
                if (true !== $this->arrData['blnTestMode']) {
                    $this->connection->insert($this->arrData['tableName'], $set);
                }
            }

            // Generate html markup for the import report table
            $htmlReport = '';

            if ($doNotSave) {
                $cssClass = 'ifcb-import-failed';
                $htmlReport .= sprintf(
                    '<tr class="%s"><td class="ifcb-td-title" colspan="2">#%s %s</td></tr>',
                    $cssClass,
                    $line,
                    $this->translator->trans('tl_import_from_csv.data_record_insert_failed', [], 'contao_default')
                );

                // Increment error counter if necessary
                ++$insertError;
            } else {
                $cssClass = 'ifcb-import-success';
                $htmlReport .= sprintf(
                    '<tr class="%s"><td class="ifcb-td-title" colspan="2">#%s %s</td></tr>',
                    $cssClass,
                    $line,
                    $this->translator->trans('tl_import_from_csv.data_record_insert_succeed', [], 'contao_default')
                );
            }

            foreach ($arrReportValues as $k => $v) {
                if (\is_array($v)) {
                    $v = serialize($v);
                }

                $htmlReport .= sprintf(
                    '<tr class="%s"><td class="col_0">%s</td><td class="col_1">%s</td></tr>',
                    $cssClass,
                    $stringUtilAdapter->substr($k, 30),
                    $stringUtilAdapter->substrHtml($v, 90)
                );
            }

            $htmlReport .= '<tr class="ifcb-delim"><td class="col_0">&nbsp;</td><td class="col_1">&nbsp;</td></tr>';

            /** @var AttributeBagInterface $bag */
            $bag = $session->getBag('markocupic_import_from_csv');

            $data = $bag->all();
            $data['rows'] .= $htmlReport;
            $bag->replace($data);
        }

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag('markocupic_import_from_csv');

        $data = $bag->all();

        $data['summary'] = [
            'rows' => $countInserts,
            'success' => $countInserts - $insertError,
            'errors' => $insertError,
        ];

        $bag->replace($data);
    }

    public function getData(string $key)
    {
        return $this->arrData[$key] ?? null;
    }

    public function setData(string $key, $varValue): void
    {
        $this->arrData[$key] = $varValue;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function getPrimaryKey(string $tableName): ?string
    {
        $stmt = $this->connection->executeQuery('SHOW INDEX FROM '.$tableName." WHERE Key_name = 'PRIMARY'");

        while (($row = $stmt->fetchAssociative()) !== false) {
            if (!empty($row['Column_name'])) {
                return $row['Column_name'];
            }
        }

        return null;
    }

    private function getDca(string $columnName, string $tableName): array
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->loadDataContainer($tableName);

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

    private function getWidgetFromDca(array $arrDca, string $columnName, string $tableName, string $varValue): Widget
    {
        $inputType = $arrDca['inputType'] ?? '';

        $objDca = new DC_Table($tableName);

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
    private function hasColumn(string $columnName, string $tableName): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns($tableName);

        return isset($columns[strtolower($columnName)]);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function addNewMemberToNewsletterRecipientList(string $tableName, string $newsletter, string $email): void
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Add new member to newsletter recipient list
        if ('tl_member' === $tableName && '' !== $email && '' !== $newsletter) {
            foreach ($stringUtilAdapter->deserialize($newsletter, true) as $newsletterId) {
                $qb = $this->connection->createQueryBuilder();
                $qb->select('id')
                    ->from('tl_newsletter_recipients', 't')
                    ->where($qb->expr()->like('t.email', ':email'))
                    ->andWhere('t.pid = :pid')
                    ->setParameters(
                        [
                            'pid' => $newsletterId,
                            'email' => $email,
                        ]
                    )
                ;

                if (!$qb->executeQuery()->rowCount()) {
                    $set = [];
                    $set['tstamp'] = time();
                    $set['pid'] = $newsletterId;
                    $set['email'] = $email;
                    $set['active'] = '1';

                    if (true !== $this->arrData['blnTestMode']) {
                        $this->connection->insert('tl_newsletter_recipients', $set);
                    }
                }
            }
        }
    }
}
