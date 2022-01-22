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
use Markocupic\ImportFromCsvBundle\Import\Field\Field;
use Markocupic\ImportFromCsvBundle\Import\Field\Formatter;
use Markocupic\ImportFromCsvBundle\Import\Field\Validator;
use function Safe\json_encode;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportFromCsv
{
    /**
     * @var array
     */
    private $arrData;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * ImportFromCsv constructor.
     */
    public function __construct(ContaoFramework $framework, Connection $connection, TranslatorInterface $translator, SessionInterface $session, Formatter $formatter, Validator $validator, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->session = $session;
        $this->formatter = $formatter;
        $this->validator = $validator;
        $this->projectDir = $projectDir;
    }

    /**
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

        /** @var $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_import_from_csv');

        $bag = $this->session->getBag('markocupic_import_from_csv');
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
        if (!ini_get('auto_detect_line_endings')) {
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
            $this->connection->executeStatement('TRUNCATE TABLE '.$tableName);
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

        // Get each line as an associative array -> array('fieldName1' => 'value1',  'fieldName2' => 'value2')
        // and store each record in the db
        $arrRecords = $stmt->process($objCsvReader);

        foreach ($arrRecords as $arrRecord) {
            $doNotSave = false;

            // Count line
            ++$line;

            // Count inserts
            ++$countInserts;

            $set = [];
            $arrReportValues = [];

            foreach ($arrRecord as $columnName => $value) {
                // Continue if field is excluded from import
                if (!\in_array($columnName, $this->arrData['selectedFields'], true)) {
                    continue;
                }

                // Autoincrement if dataRecords are appended
                if ('append_entries' === $this->arrData['importMode'] && strtolower($columnName) === strtolower($this->arrData['primaryKey'])) {
                    continue;
                }

                // Create field object
                $objField = new Field($tableName, $columnName, $arrRecord);

                $objField->setValue(trim((string) $value));

                // Get the DCA of the current field
                $arrDca = $this->getDca($objField->getTableName(), $objField->getName());
                $objField->setDca($arrDca);

                // Prepare FormWidget object set inputType to "text" if there is no definition
                $inputType = ($arrDca['inputType'] ?? null) && '' !== $arrDca['inputType'] ? $arrDca['inputType'] : 'text';
                $objField->setInputType($inputType);

                // Map checkboxWizards to regular checkbox widgets
                if ('checkboxWizard' === $objField->getInputType()) {
                    $objField->setInputType('checkbox');
                }

                // HOOK: add custom validation
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && \is_array($GLOBALS['TL_HOOKS']['importFromCsv'])) {
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback) {
                        $systemAdapter->importStatic($callback[0])->{$callback[1]}($objField, $line, $this);
                    }
                }

                // Set correct date format
                $this->formatter->setCorrectDateFormat($objField);

                // Use form widgets for input validation
                $objWidget = $this->getWidgetFromInputType($objField->getInputType(), $objField->getName(), $objField->getValue(), $objField->getDca());

                // Use the form widget for input validation
                if ($objWidget) {
                    $objField->setWidget($objWidget);

                    if ($objWidget->hasErrors()) {
                        $objField->addErrors($objWidget->getErrors());
                    }

                    $this->formatter->convertToArray($objField, $strArrayDelimiter);

                    // Validate dates
                    $this->validator->validate($objField);

                    // Special treatment for password
                    if ('password' === $objField->getInputType()) {
                        // @see Contao\FormPassword::construct() Line 66
                        $objWidget->useRawRequestData = false;
                        $inputAdapter->setPost('password_confirm', $objField->getValue());
                    }


                    $objWidget->value = $objField->getValue();

                    // Set POST, so the content can be validated
                    $inputAdapter->setPost($objField->getName(), $objField->getValue());

                    // Skip validation for selected fields
                    if (!\in_array($objField->getName(), $arrSkipValidationFields, true)) {
                        // Validate input
                        if (!$objWidget->validate()) {
                            $objField->addErrors($objWidget->getErrors());
                        }
                    }
                }

                $this->validator->checkIsUnique($objField);


                if ($objField->hasErrors()) {
                    $doNotSave = true;

                    $value = sprintf(
                        '"%s" => <span class="ifcb-error-msg">%s</span>',
                        $objField->getValue(),
                        $objField->getErrorsAsString(),
                    );

                    // Replace value with the error message if there is one
                    $objField->setValue($value);
                }

                // Get data for the report
                $arrReportValues[$objField->getName()] = $objField->getValue();

                // Convert arrays to serialized string
                if (\is_array($objField->getValue())) {
                    $arrReportValues[$objField->getName()] = print_r($objField->getValue(),true);
                    $objField->setValue(serialize($objField->getValue()));
                }

                $this->formatter->setCorrectEmptyValue($objField);
                $this->formatter->convertDateToTimestamp($objField);
                $this->formatter->encodePassword($objField);

                // Replace all '[NEWLINE]' tags with the end of line tag
                $set[$objField->getName()] = str_replace('[NEWLINE]', PHP_EOL, (string) $objField->getValue());
            }// End foreach

            // Insert data record
            if (!$doNotSave) {

                // Auto insert "tstamp"
                if ($this->hasColumn('tstamp', $tableName)) {
                    if (!isset($set['tstamp']) || '' === $set['tstamp']) {
                        $set['tstamp'] = time();
                        $arrReportValues['tstamp'] = time();
                    }
                }

                // Auto insert "dateAdded"
                if ($this->hasColumn('dateAdded', $tableName)) {

                    if (!isset($set['dateAdded']) || '' === $set['dateAdded']) {
                        $set['dateAdded'] = time();
                        $arrReportValues['dateAdded'] = date($configAdapter->get('dateFormat'), time());
                    }
                }

                // Add to newsletter
                if (($set['newsletter'] ?? null) && '' !== $set['newsletter'] && ($set['email'] ?? null) && '' !== $set['email']) {
                    $this->addNewMemberToNewsletterRecipientList($tableName, $set['newsletter'], $set['email']);
                }

                // Add new record to the database
                if (true !== $this->arrData['blnTestMode']) {
                    $this->connection->insert($tableName, $set);
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
                    $this->translator->trans('tl_import_from_csv.datarecordInsertFailed', [], 'contao_default')
                );

                // Increment error counter if necessary
                ++$insertError;
            } else {
                $cssClass = 'ifcb-import-success';
                $htmlReport .= sprintf(
                    '<tr class="%s"><td class="ifcb-td-title" colspan="2">#%s %s</td></tr>',
                    $cssClass,
                    $line,
                    $this->translator->trans('tl_import_from_csv.datarecordInsertSucceed', [], 'contao_default')
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

            $bag = $this->session->getBag('markocupic_import_from_csv');
            $data = $bag->all();
            $data['rows'] .= $htmlReport;
            $bag->replace($data);
        }

        $bag = $this->session->getBag('markocupic_import_from_csv');
        $data = $bag->all();
        $data['summary'] = [
            'rows' => $countInserts,
            'success' => $countInserts - $insertError,
            'errors' => $insertError,
        ];
        $bag->replace($data);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
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

    private function getDca(string $tableName, string $columnName): array
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->loadDataContainer($tableName);
        $arrDca = &$GLOBALS['TL_DCA'][$tableName]['fields'][$columnName];

        return \is_array($arrDca) ? $arrDca : [];
    }

    private function getWidgetFromInputType(string $inputType, string $columnName, ?string $value, array $arrDca): ?Widget
    {
        $strClass = &$GLOBALS['TL_FFL'][$inputType];

        if (class_exists($strClass)) {
            return new $strClass($strClass::getAttributesFromDca($arrDca, $columnName, $value, '', '', $this));
        }

        return null;
    }

    private function hasColumn(string $strColumn, string $strTable): bool
    {
        $schemaManager = $this->connection->getSchemaManager();

        if (!$schemaManager->tablesExist([$strTable])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns($strTable);

        return isset($columns[strtolower($strColumn)]);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function addNewMemberToNewsletterRecipientList(string $strTableName, string $newsletter, string $email): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Add new member to newsletter recipient list
        if ('tl_member' === $strTableName && '' !== $email && '' !== $newsletter) {
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

                if (!$qb->execute()->rowCount()) {
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
