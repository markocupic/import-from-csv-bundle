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

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\File;
use Contao\FrontendUser;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use Markocupic\ImportFromCsvBundle\Import\Field\Field;
use Markocupic\ImportFromCsvBundle\Import\Field\FieldFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ImportFromCsv.
 */
class ImportFromCsv
{
    const SESSION_BAG_KEY = 'import_from_csv';

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
     * @var EncoderFactoryInterface
     */
    private $encoderFactory;

    /**
     * @var FieldFactory
     */
    private $fieldFactory;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * ImportFromCsv constructor.
     */
    public function __construct(ContaoFramework $framework, Connection $connection, TranslatorInterface $translator, SessionInterface $session, EncoderFactoryInterface $encoderFactory, FieldFactory $fieldFactory, string $rootDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->session = $session;
        $this->encoderFactory = $encoderFactory;
        $this->fieldFactory = $fieldFactory;
        $this->rootDir = $rootDir;
    }

    /**
     * @throws Exception
     */
    public function importCsv(File $objCsvFile, string $tablename, string $strImportMode, array $arrSelectedFields = [], string $strDelimiter = ';', string $strEnclosure = '"', string $strArrayDelimiter = '||', bool $blnTestMode = false, array $arrSkipValidationFields = [], int $intOffset = 0, int $intLimit = 0): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        if (TL_MODE === 'BE') {
            $bag = $this->session->getBag('contao_backend');
            $bag[self::SESSION_BAG_KEY]['report'][] = $htmlReport;
            $this->session->set('contao_backend', $bag);
        }

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
        $objCsvReader = Reader::createFromPath($this->rootDir.'/'.$objCsvFile->path, 'r');

        // Set the CSV header offset
        $objCsvReader->setHeaderOffset(0);

        // Set the delimiter string
        $objCsvReader->setDelimiter($strDelimiter);

        // Set enclosure string
        $objCsvReader->setEnclosure($strEnclosure);

        // Get the primary key
        $strPrimaryKey = $this->getPrimaryKey($tablename);

        if (null === $strPrimaryKey) {
            throw new \Exception('No primary key found in '.$tablename);
        }

        // Load language file
        $systemAdapter->loadLanguageFile($tablename);

        // Store the options in $this->arrData
        $this->arrData = [
            'objCsvFile' => $objCsvFile,
            'tablename' => $tablename,
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
            $this->connection->executeStatement('TRUNCATE TABLE '.$tablename);
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

        // Get each line as an associative array -> array('fieldname1' => 'value1',  'fieldname2' => 'value2')
        // and store each record in the db
        $arrRecords = $stmt->process($objCsvReader);

        foreach ($arrRecords as $arrRecord) {
            $doNotSave = false;

            // Count line
            ++$line;

            // Count inserts
            ++$countInserts;

            $set = [];

            foreach ($arrRecord as $fieldname => $value) {
                // Continue if field is excluded from import
                if (!\in_array($fieldname, $this->arrData['selectedFields'], true)) {
                    continue;
                }

                // Autoincrement id datarecords are appended
                if ('append_entries' === $this->arrData['importMode'] && strtolower($fieldname) === strtolower($this->arrData['primaryKey'])) {
                    continue;
                }

                // Create field object
                $objField = $this->fieldFactory->getField($tablename, $fieldname, $arrRecord);
                $objField->setValue(trim((string) $value));

                // Get the DCA of the current field

                $arrDcaField = $this->getDca($objField->getTablename(), $objField->getName());
                $objField->setDca($arrDcaField);

                // Prepare FormWidget object set inputType to "text" if there is no definition
                $inputType = !empty($arrDcaField['inputType']) ? $arrDcaField['inputType'] : 'text';
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

                    if ($objField->hasErrors()) {
                        $objField->setValue(implode(' ', $objField->getErrors()));
                        $doNotSave = true;
                    }

                    if ($objField->getDoNotSave()) {
                        $doNotSave = true;
                    }
                }

                // Use form widgets for input validation
                $objWidget = $this->getWidgetFromInputType($objField->getInputType(), $objField->getName(), $objField->getValue(), $objField->getDca());

                // Use form widgets for input validation
                if ($objWidget && !$objField->getSkipWidgetValidation()) {
                    // Set POST, so the content can be validated
                    $inputAdapter->setPost($objField->getName(), $objField->getValue());

                    // Special treatment for password
                    if ('password' === $objField->getInputType()) {
                        // @see Contao\FormPassword::construct() Line 66
                        $objWidget->useRawRequestData = false;
                        $inputAdapter->setPost('password_confirm', $objField->getValue());
                    }

                    if ($arrDcaField['eval']['multiple']) {
                        // Convert CSV fields
                        if (isset($arrDcaField['eval']['csv'])) {
                            if (null === $objField->getValue() || '' === $objField->getValue()) {
                                $objField->setValue([]);
                            } else {
                                $objField->setValue(explode($arrDcaField['eval']['csv'], $objField->getValue()));
                            }
                        } elseif (false !== strpos($objField->getValue(), $strArrayDelimiter)) {
                            // Value is e.g. 3||4
                            $objField->setValue(explode($strArrayDelimiter, $objField->getValue()));
                        } else {
                            // The value is a serialized array or simple value e.g 3
                            $objField->setValue($stringUtilAdapter->deserialize($objField->getValue(), true));
                        }

                        $inputAdapter->setPost($objField->getName(), $objField->getValue());
                        $objWidget->value = $objField->getValue();
                    }

                    // !!! SECURITY !!! SKIP VALIDATION FOR SELECTED FIELDS
                    if (!\in_array($objField->getName(), $arrSkipValidationFields, true)) {
                        // Validate input
                        $objWidget->validate();
                    }

                    $objField->setValue($objWidget->value);

                    // Convert date formats into timestamps
                    $rgxp = $arrDcaField['eval']['rgxp'];

                    if (('date' === $rgxp || 'time' === $rgxp || 'datim' === $rgxp) && '' !== $objField->getValue() && !$objWidget->hasErrors()) {
                        try {
                            $strTimeFormat = $configAdapter->get($rgxp.'Format');
                            $objDate = new Date($objField->getValue(), $strTimeFormat);
                            $objField->setValue($objDate->tstamp);
                        } catch (\OutOfBoundsException $e) {
                            $objWidget->addError(
                                sprintf(
                                    $this->translator->trans('ERR.invalidDate', [], 'contao_default'),
                                    $objField->getValue()
                                )
                            );
                        }
                    }

                    // !!! SECURITY !!! SKIP UNIQUE VALIDATION FOR SELECTED FIELDS
                    if (!\in_array($objField->getName(), $arrSkipValidationFields, true)) {
                        // Make sure that unique fields are unique
                        if ($arrDcaField['eval']['unique'] && '' !== $objField->getValue() && !$this->isUniqueValue($objField->getTablename(), $objField->getName(), $objField->getValue())) {
                            $objWidget->addError(
                                sprintf(
                                    $this->translator->trans('ERR.unique', [], 'contao_default'),
                                    $arrDcaField['label'][0] ?: $objField->getName()
                                )
                            );
                        }
                    }

                    // Do not save the field if there are errors
                    if ($objWidget->hasErrors()) {
                        $doNotSave = true;

                        $value = sprintf(
                            '"%s" => <span class="errMsg">%s</span>',
                            $objField->getValue(),
                            $objWidget->getErrorsAsString()
                        );
                        $objField->setValue($value);
                    } else {
                        // Set the correct empty value
                        if ('' === $objField->getValue()) {
                            $objField->setValue($objWidget->getEmptyValue());
                            // Set the correct empty value
                            if (empty($objField->getValue())) {
                                /*
                                 * Hack Because Contao doesn't handle correct empty string input f.ex username
                                 * @see https://github.com/contao/core-bundle/blob/master/src/Resources/contao/library/Contao/Widget.php#L1526-1527
                                 */
                                if (isset($arrDcaField['sql'])) {
                                    $sql = $arrDcaField['sql'];

                                    if (false === strpos($sql, 'NOT NULL')) {
                                        if (false !== strpos($sql, 'NULL')) {
                                            $objField->setValue(null);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Encode password, if validation was skipped
                if ('password' === $arrDcaField['inputType']) {
                    if (!empty($objField->getValue())) {
                        if ($objField->getValue() === $arrRecord[$objField->getName()]) {
                            if ('tl_user' === $objField->getTablename()) {
                                $encoder = $this->encoderFactory->getEncoder(BackendUser::class);
                            } else {
                                $encoder = $this->encoderFactory->getEncoder(FrontendUser::class);
                            }
                            $objField->setValue($encoder->encodePassword($objField->getValue(), null));
                        }
                    }
                }

                // Convert arrays to CSV or serialized strings
                if (\is_array($objField->getValue())) {
                    if (isset($arrDcaField['eval']['csv'])) {
                        $value = implode($arrDcaField['eval']['csv'], $objField->getValue());
                    } else {
                        $value = serialize($objField->getValue());
                    }
                    $objField->setValue($value);
                }

                // Replace all '[NEWLINE]' tags with the end of line tag
                $set[$objField->getName()] = str_replace('[NEWLINE]', PHP_EOL, (string) $objField->getValue());
            }

            // Insert data record
            if (!$doNotSave) {
                // Insert tstamp
                if ($this->hasColumn('tstamp', $objField->getTablename())) {
                    if (!$set['tstamp'] > 0) {
                        $set['tstamp'] = time();
                    }
                }

                // Insert dateAdded (tl_member)
                if ($this->hasColumn('dateAdded', $objField->getTablename())) {
                    if (!\strlen((string) $set['dateAdded'])) {
                        $set['dateAdded'] = time();
                    }
                }

                // Add to newsletter
                if (!empty($set['newsletter']) && !empty($set['email'])) {
                    $this->addNewMemberToNewsletterRecipientList($objField, $set['newsletter'], $set['email']);
                }

                // Insert datarecord
                if (true !== $this->arrData['blnTestMode']) {
                    // Insert entry into database
                    $this->connection->insert($objField->getTablename(), $set);
                }
            }

            // Generate html markup for the import report table
            $htmlReport = '';
            $cssClass = 'allOk';

            if ($doNotSave) {
                $cssClass = 'error';
                $htmlReport .= sprintf(
                    '<tr class="%s"><td class="tdTitle" colspan="2">#%s %s</td></tr>',
                    $cssClass,
                    $line,
                    $this->translator->trans('tl_import_from_csv.datarecordInsertFailed', [], 'contao_default')
                );

                // Increment error counter if necessary
                ++$insertError;
            } else {
                $htmlReport .= sprintf(
                    '<tr class="%s"><td class="tdTitle" colspan="2">#%s %s</td></tr>',
                    $cssClass,
                    $line,
                    $this->translator->trans('tl_import_from_csv.datarecordInsertSucceed', [], 'contao_default')
                );
            }

            foreach ($set as $k => $v) {
                if (\is_array($v)) {
                    $v = serialize($v);
                }
                $htmlReport .= sprintf(
                    '<tr class="%s"><td>%s</td><td>%s</td></tr>',
                    $cssClass,
                    $stringUtilAdapter->substr($k, 30),
                    $stringUtilAdapter->substrHtml($v, 90)
                );
            }

            $htmlReport .= '<tr class="delim"><td>&nbsp;</td><td>&nbsp;</td></tr>';

            if (TL_MODE === 'BE') {
                $bag = $this->session->getBag('contao_backend');
                $bag[self::SESSION_BAG_KEY]['report'][] = $htmlReport;
                $this->session->set('contao_backend', $bag);
            }
        }// end foreach

        if (TL_MODE === 'BE') {
            $bag = $this->session->getBag('contao_backend');
            $bag[self::SESSION_BAG_KEY]['status'] = [
                'blnTestMode' => $blnTestMode,
                'rows' => $countInserts,
                'success' => $countInserts - $insertError,
                'errors' => $insertError,
                'offset' => $intOffset > 0 ? $intOffset : '-',
                'limit' => $intLimit > 0 ? $intLimit : '-',
            ];
            $this->session->set(self::SESSION_BAG_KEY, $bag);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getPrimaryKey(string $tablename): ?string
    {
        $stmt = $this->connection->executeQuery('SHOW INDEX FROM '.$tablename." WHERE Key_name = 'PRIMARY'");

        foreach ($stmt->fetchAll() as $row)  {
            if (!empty($row['Column_name'])) {
                return $row['Column_name'];
            }
        }

        return null;
    }

    private function getDca(string $tablename, string $fieldname): array
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->loadDataContainer($tablename);
        $arrDcaField = &$GLOBALS['TL_DCA'][$tablename]['fields'][$fieldname];

        return \is_array($arrDcaField) ? $arrDcaField : [];
    }

    private function getWidgetFromInputType(string $inputType, string $fieldname, ?string $value, array $arrDca): ?Widget
    {
        $strClass = &$GLOBALS['TL_FFL'][$inputType];

        if (class_exists($strClass)) {
            return new $strClass($strClass::getAttributesFromDca($arrDca, $fieldname, $value, '', '', $this));
        }

        return null;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function addNewMemberToNewsletterRecipientList(Field $objField, string $newsletter, string $email): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Add new member to newsletter recipient list
        if ('tl_member' === $objField->getTablename() && '' !== $email && '' !== $newsletter) {
            foreach ($stringUtilAdapter->deserialize($newsletter, true) as $newsletterId) {
                $count = $this->connection->executeStatement(
                    'SELECT id FROM tl_newsletter_recipients WHERE email = ? AND pid = (SELECT pid FROM tl_newsletter_recipients WHERE id = ?) AND id != ?',
                        [
                            $email,
                            $newsletterId,
                            $newsletterId,
                        ]
                    )
                ;

                if (!$count) {
                    $set = [];
                    $set['tstamp'] = time();
                    $set['pid'] = $newsletterId;
                    $set['email'] = $email;
                    $set['active'] = '1';

                    if (true !== $blnTestMode) {
                        $this->connection->insert('tl_newsletter_recipients', $set);
                    }
                }
            }
        }
    }

    private function hasColumn(string $strColumn, string $strTable): bool
    {
        $schemaManager = $this->connection->getSchemaManager();

        // If the database table itself return false
        if (!$schemaManager->tablesExist([$strTable])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns($strTable);

        return isset($columns[strtolower($strColumn)]);
    }

    /**
     * @param $value
     */
    private function isUniqueValue(string $strTable, string $strColumn, $value, ?int $intId = null): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('id')
            ->from($strTable, 't')
            ->where('t.'.$strColumn.' = :value')
            ->setParameter('value', $value)
        ;

        if (null !== $intId) {
            $qb->andWhere('t.id != :id');
            $qb->setParameter('id', $intId);
        }

        $qb->setMaxResults(1);
        $stmt = $qb->execute();

        return !empty($stmt->fetchAll()) ? false : true;
    }
}
