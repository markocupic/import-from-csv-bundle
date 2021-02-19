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
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * Class ImportFromCsv.
 */
class ImportFromCsv
{
    /**
     * @var array
     */
    protected $arrData;
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var EncoderFactoryInterface
     */
    private $encoderFactory;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * ImportFromCsv constructor.
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, EncoderFactoryInterface $encoderFactory, string $rootDir)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->encoderFactory = $encoderFactory;
        $this->rootDir = $rootDir;
    }

    /**
     * @throws Exception
     */
    public function importCsv(File $objCsvFile, string $strTable, string $strImportMode, array $arrSelectedFields = [], string $strDelimiter = ';', string $strEnclosure = '"', string $strArrayDelimiter = '||', bool $blnTestMode = false, array $arrSkipValidationFields = [], int $intOffset = 0, int $intLimit = 0): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        if (TL_MODE === 'BE') {
            $bag = $this->session->getBag('contao_backend');
            $bag['import_from_csv']['report'][] = $htmlReport;
            $this->session->set('contao_backend', $bag);
        }

        if (empty($strDelimiter)) {
            $strDelimiter = ';';
        }

        if (empty($strEnclosure)) {
            $strEnclosure = '"';
        }

        if (empty($strArrayDelimiter)) {
            $strArrayDelimiter = '||';
        }

        // Throw a Exception exception if the submitted string length is not equal to 1 byte.
        if (\strlen($strDelimiter) > 1) {
            throw new \Exception(sprintf('%s expects field delimiter to be a single character. %s given.', __METHOD__, $strDelimiter));
        }

        // Throw a Exception exception if the submitted string length is not equal to 1 byte.
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

        // Set Enclosure string
        $objCsvReader->setEnclosure($strEnclosure);

        // Get the primary key
        $strPrimaryKey = $this->getPrimaryKey($strTable);

        if (null === $strPrimaryKey) {
            throw new \Exception('No primary key found in '.$strTable);
        }

        // Load language file
        $systemAdapter->loadLanguageFile($strTable);

        // Load dca
        $controllerAdapter->loadDataContainer($strTable);

        // Store the options in $this->arrData
        $this->arrData = [
            'objCsvFile' => $objCsvFile,
            'tablename' => $strTable,
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
            $databaseAdapter->getInstance()->execute('TRUNCATE TABLE `'.$strTable.'`');
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

        // Get ech line as an associative array array('fieldname1' => 'value1',  'fieldname2' => 'value2')
        // and store each record in the db
        $arrRecords = $stmt->process($objCsvReader);

        foreach ($arrRecords as $arrRecord) {
            $doNotSave = false;

            // Count line
            ++$line;

            // Count inserts
            ++$countInserts;

            $set = [];

            foreach ($arrRecord as $fieldName => $fieldValue) {
                $blnCustomValidation = false;

                // Continue if field is excluded from import
                if (!\in_array($fieldName, $this->arrData['selectedFields'], true)) {
                    continue;
                }

                // If entries are appended autoincrement id
                if ('append_entries' === $this->arrData['importMode'] && strtolower($fieldName) === strtolower($this->arrData['primaryKey'])) {
                    continue;
                }

                if (null === $fieldValue) {
                    $fieldValue = '';
                }

                // Convert variable to a string
                $fieldValue = (string) $fieldValue;

                // Get the DCA of the current field
                $arrDCA = &$GLOBALS['TL_DCA'][$strTable]['fields'][$fieldName];
                $arrDCA = \is_array($arrDCA) ? $arrDCA : [];

                // Prepare FormWidget object set inputType to "text" if there is no definition
                $inputType = '' !== $arrDCA['inputType'] ? $arrDCA['inputType'] : 'text';

                // Map checkboxWizards to regular checkbox widgets
                if ('checkboxWizard' === $inputType) {
                    $inputType = 'checkbox';
                }
                $strClass = &$GLOBALS['TL_FFL'][$inputType];

                // HOOK: add custom validation
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && \is_array($GLOBALS['TL_HOOKS']['importFromCsv'])) {
                    $arrCustomValidation = [
                        'strTable' => $strTable,
                        'arrDCA' => $arrDCA,
                        'fieldname' => $fieldName,
                        'value' => $fieldValue,
                        'arrayLine' => $arrRecord,
                        'line' => $line,
                        'objCsvFile' => $objCsvFile,
                        'objCsvReader' => $objCsvReader,
                        'skipWidgetValidation' => false,
                        'hasErrors' => false,
                        'errorMsg' => null,
                        'doNotSave' => false,
                    ];

                    $blnCustomValidation = false;

                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback) {
                        $arrCustomValidation = $systemAdapter->importStatic($callback[0])->{$callback[1]}($arrCustomValidation, $this);

                        if (!\is_array($arrCustomValidation)) {
                            throw new \Exception('Expected array as return value.');
                        }
                        $fieldValue = $arrCustomValidation['value'];

                        // Check if widget-validation should be skipped
                        if (true === $arrCustomValidation['skipWidgetValidation']) {
                            $blnCustomValidation = true;
                        }
                    }

                    if ('' !== $arrCustomValidation['errorMsg']) {
                        $fieldValue = $arrCustomValidation['errorMsg'];
                        $doNotSave = true;
                    }

                    if ($arrCustomValidation['doNotSave']) {
                        $doNotSave = true;
                    }
                }

                // Continue if the class does not exist
                // Use form widgets for input validation
                if (class_exists($strClass) && true !== $blnCustomValidation) {
                    $objWidget = new $strClass($strClass::getAttributesFromDca($arrDCA, $fieldName, $fieldValue, '', '', $this));
                    $objWidget->storeValues = false;

                    // Set post var, so the content can be validated
                    $inputAdapter->setPost($fieldName, $fieldValue);

                    // Special treatment for password
                    if ('password' === $arrDCA['inputType']) {
                        // @see Contao\FormPassword::construct() Line 66
                        $objWidget->useRawRequestData = false;
                        $inputAdapter->setPost('password_confirm', $fieldValue);
                    }

                    // Add option values in the csv like this: value1||value2||value3
                    if ($arrDCA['eval']['multiple']) {
                        // Convert CSV fields
                        if (isset($arrDCA['eval']['csv'])) {
                            if (empty($fieldValue)) {
                                $fieldValue = [];
                            } elseif (empty(trim($fieldValue))) {
                                $fieldValue = [];
                            } else {
                                $fieldValue = explode($arrDCA['eval']['csv'], $fieldValue);
                            }
                        } elseif (!empty($fieldValue) && \is_array($stringUtilAdapter->deserialize($fieldValue))) {
                            // The value is serialized array
                            $fieldValue = $stringUtilAdapter->deserialize($fieldValue);
                        } else {
                            // Add option values in the csv like this: value1||value2||value3
                            $fieldValue = '' !== $fieldValue ? explode($strArrayDelimiter, $fieldValue) : [];
                        }

                        $inputAdapter->setPost($fieldName, $fieldValue);
                        $objWidget->value = $fieldValue;
                    }

                    // !!! SECURITY !!! SKIP VALIDATION FOR SELECTED FIELDS
                    if (!\in_array($fieldName, $arrSkipValidationFields, true)) {
                        // Validate input
                        $objWidget->validate();
                    }

                    $fieldValue = $objWidget->value;

                    // Convert date formats into timestamps
                    $rgxp = $arrDCA['eval']['rgxp'];

                    if (('date' === $rgxp || 'time' === $rgxp || 'datim' === $rgxp) && '' !== $fieldValue && !$objWidget->hasErrors()) {
                        try {
                            $strTimeFormat = $GLOBALS['TL_CONFIG'][$rgxp.'Format'];
                            $objDate = new Date($fieldValue, $strTimeFormat);
                            $fieldValue = $objDate->tstamp;
                        } catch (\OutOfBoundsException $e) {
                            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $fieldValue));
                        }
                    }

                    // !!! SECURITY !!! SKIP UNIQUE VALIDATION FOR SELECTED FIELDS
                    if (!\in_array($fieldName, $arrSkipValidationFields, true)) {
                        // Make sure that unique fields are unique
                        if ($arrDCA['eval']['unique'] && '' !== $fieldValue && !$databaseAdapter->getInstance()->isUniqueValue($strTable, $fieldName, $fieldValue, null)) {
                            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], $arrDCA['label'][0] ?: $fieldName));
                        }
                    }

                    // Do not save the field if there are errors
                    if ($objWidget->hasErrors()) {
                        $doNotSave = true;

                        $fieldValue = sprintf('"%s" => <span class="errMsg">%s</span>', $fieldValue, $objWidget->getErrorsAsString());
                    } else {
                        // Set the correct empty value
                        if (empty($fieldValue)) {
                            $fieldValue = $objWidget->getEmptyValue();
                            // Set the correct empty value
                            if (empty($fieldValue)) {
                                /*
                                 * Hack Because Contao doesn't handle correct empty string input f.ex username
                                 * @see https://github.com/contao/core-bundle/blob/master/src/Resources/contao/library/Contao/Widget.php#L1526-1527
                                 */
                                if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$fieldName]['sql'])) {
                                    $sql = $GLOBALS['TL_DCA'][$strTable]['fields'][$fieldName]['sql'];

                                    if (false === strpos($sql, 'NOT NULL')) {
                                        if (false !== strpos($sql, 'NULL')) {
                                            $fieldValue = null;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Encode password, if validation was skipped
                if ('password' === $arrDCA['inputType']) {
                    if (!empty($fieldValue)) {
                        if ($fieldValue === $arrRecord[$fieldName]) {
                            if ('tl_user' === $strTable) {
                                $encoder = $this->encoderFactory->getEncoder(BackendUser::class);
                            } else {
                                $encoder = $this->encoderFactory->getEncoder(FrontendUser::class);
                            }
                            $fieldValue = $encoder->encodePassword($fieldValue, null);
                        }
                    }
                }

                // Convert arrays to CSV or serialized strings
                if (\is_array($fieldValue)) {
                    if (isset($arrDCA['eval']['csv'])) {
                        $fieldValue = implode($arrDCA['eval']['csv'], $fieldValue);
                    } else {
                        $fieldValue = serialize($fieldValue);
                    }
                }

                // Replace all '[NEWLINE]' tags with the end of line tag
                $set[$fieldName] = str_replace('[NEWLINE]', PHP_EOL, (string) $fieldValue);
            }

            // Insert data record
            if (!$doNotSave) {
                // Insert tstamp
                if ($databaseAdapter->getInstance()->fieldExists('tstamp', $strTable)) {
                    if (!$set['tstamp'] > 0) {
                        $set['tstamp'] = time();
                    }
                }

                // Insert dateAdded (tl_member)
                if ($databaseAdapter->getInstance()->fieldExists('dateAdded', $strTable)) {
                    if (!$set['dateAdded'] > 0) {
                        $set['dateAdded'] = time();
                    }
                }

                // Add new member to newsletter recipient list
                if ('tl_member' === $strTable && !empty($set['email']) && !empty($set['newsletter'])) {
                    foreach ($stringUtilAdapter->deserialize($set['newsletter'], true) as $newsletterId) {
                        // Check for unique email-address
                        $objRecipient = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_newsletter_recipients WHERE email=? AND pid=(SELECT pid FROM tl_newsletter_recipients WHERE id=?) AND id!=?')->execute($set['email'], $newsletterId, $newsletterId);

                        if (!$objRecipient->numRows) {
                            $arrRecipient = [];
                            $arrRecipient['tstamp'] = time();
                            $arrRecipient['pid'] = $newsletterId;
                            $arrRecipient['email'] = $set['email'];
                            $arrRecipient['active'] = '1';

                            if (true !== $blnTestMode) {
                                $databaseAdapter->getInstance()
                                    ->prepare('INSERT INTO tl_newsletter_recipients %s')
                                    ->set($arrRecipient)
                                    ->execute()
                                ;
                            }
                        }
                    }
                }

                try {
                    if (true !== $blnTestMode) {
                        // Insert entry into database
                        $databaseAdapter->getInstance()->prepare('INSERT INTO '.$strTable.' %s')->set($set)->execute();
                    }
                } catch (\Exception $e) {
                    $set['insertError'] = $e->getMessage();
                    $doNotSave = true;
                }
            }

            // Generate html markup for the import report table
            $htmlReport = '';
            $cssClass = 'allOk';

            if ($doNotSave) {
                $cssClass = 'error';
                $htmlReport .= sprintf('<tr class="%s"><td class="tdTitle" colspan="2">#%s Datensatz konnte nicht angelegt werden!</td></tr>', $cssClass, $line);

                // Increment error counter if necessary
                ++$insertError;
            } else {
                $htmlReport .= sprintf('<tr class="%s"><td class="tdTitle" colspan="2">#%s Datensatz erfolgreich angelegt!</td></tr>', $cssClass, $line);
            }

            foreach ($set as $k => $v) {
                if (\is_array($v)) {
                    $v = serialize($v);
                }
                $htmlReport .= sprintf('<tr class="%s"><td>%s</td><td>%s</td></tr>', $cssClass, $stringUtilAdapter->substr($k, 30), $stringUtilAdapter->substrHtml($v, 90));
            }

            $htmlReport .= '<tr class="delim"><td>&nbsp;</td><td>&nbsp;</td></tr>';

            if (TL_MODE === 'BE') {
                $bag = $this->session->getBag('contao_backend');
                $bag['import_from_csv']['report'][] = $htmlReport;
                $this->session->set('contao_backend', $bag);
            }
        }// end foreach

        if (TL_MODE === 'BE') {
            $bag = $this->session->getBag('contao_backend');
            $bag['import_from_csv']['status'] = [
                'blnTestMode' => $blnTestMode ? true : false,
                'rows' => $countInserts,
                'success' => $countInserts - $insertError,
                'errors' => $insertError,
                'offset' => $intOffset > 0 ? $intOffset : '-',
                'limit' => $intLimit > 0 ? $intLimit : '-',
            ];
            $this->session->set('import_from_csv', $bag);
        }
    }

    /**
     * @param $strTable
     *
     * @return mixed|null
     */
    private function getPrimaryKey(string $strTable): ?string
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        $objDb = $databaseAdapter->getInstance()->execute('SHOW INDEX FROM '.$strTable." WHERE Key_name = 'PRIMARY'");

        if ($objDb->numRows) {
            if ('' !== $objDb->Column_name) {
                return $objDb->Column_name;
            }
        }

        return null;
    }
}
