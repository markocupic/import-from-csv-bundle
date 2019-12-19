<?php

/**
 * Import from csv bundle: Backend module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package import-from-csv-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

declare(strict_types=1);

namespace Markocupic\ImportFromCsv;

use Contao\BackendUser;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\File;
use Contao\FrontendUser;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Class ImportFromCsv
 * @package Markocupic\ImportFromCsv
 */
class ImportFromCsv
{
    /**
     * @var array
     */
    protected $arrData;

    /**
     * @param File $objCsvFile
     * @param string $strTable
     * @param string $strImportMode
     * @param array|null $arrSelectedFields
     * @param string $strDelimiter
     * @param string $strEnclosure
     * @param string $strArrayDelimiter
     * @param bool $blnTestMode
     * @param array $arrSkipValidationFields
     * @param int $offset
     * @param int $limit
     * @throws \League\Csv\Exception
     */
    public function importCsv(File $objCsvFile, string $strTable, string $strImportMode, array $arrSelectedFields = null, string $strDelimiter = ';', string $strEnclosure = '"', string $strArrayDelimiter = '||', bool $blnTestMode = false, array $arrSkipValidationFields = array(), int $offset = 0, int $limit = 0): void
    {
        // If the CSV document was created or is read on a Macintosh computer,
        // add the following lines before using the library to help PHP detect line ending in Mac OS X.
        if (!ini_get("auto_detect_line_endings"))
        {
            ini_set("auto_detect_line_endings", '1');
        }

        // Get the project directory
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Get the League\Csv\Reader object
        $objCsvReader = Reader::createFromPath($rootDir . '/' . $objCsvFile->path, 'r');

        // Set the CSV header offset
        $objCsvReader->setHeaderOffset(0);

        // Set the delimiter string
        $objCsvReader->setDelimiter($strDelimiter);

        // Set Enclosure string
        $objCsvReader->setEnclosure($strEnclosure);

        // Get fieldnames
        $arrFieldnames = $objCsvReader->getHeader();

        // Get the primary key
        $strPrimaryKey = $this->getPrimaryKey($strTable);
        if ($strPrimaryKey === null)
        {
            throw new \Exception('No primary key found in ' . $strTable);
        }

        // Store sucess or failure message in the session
        $_SESSION['import_from_csv']['report'] = array();

        // Load language file
        System::loadLanguageFile($strTable);

        // Load dca
        Controller::loadDataContainer($strTable);

        // Store the options in $this->arrData
        $this->arrData = array(
            'tablename'      => $strTable,
            'primaryKey'     => $strPrimaryKey,
            'importMode'     => $strImportMode,
            'selectedFields' => is_array($arrSelectedFields) ? $arrSelectedFields : array(),
            'fieldSeparator' => $strDelimiter,
            'fieldEnclosure' => $strEnclosure,
        );

        // Truncate table
        if ($this->arrData['importMode'] == 'truncate_table' && $blnTestMode === false)
        {
            Database::getInstance()->execute('TRUNCATE TABLE `' . $strTable . '`');
        }

        if (count($this->arrData['selectedFields']) < 1)
        {
            return;
        }

        // Count inserts (depends on offset and limit and is not equal to $row)
        $countInserts = 0;

        // Count errors
        $insertError = 0;

        // Get Line (Header is line 0)
        $line = $offset;

        // Get the League\Csv\Statement object
        $stmt = new Statement();

        // Set offset
        if ($offset > 0)
        {
            $stmt = $stmt->offset($offset);
        }

        // Set limit
        if ($limit > 0)
        {
            $stmt = $stmt->limit($limit);
        }

        $arrRecords = $stmt->process($objCsvReader);

        // Get ech line as an associative array array('fieldname1' => 'value1',  'fieldname2' => 'value2')
        // and store each record in the db
        foreach ($arrRecords as $arrRecord)
        {
            $doNotSave = false;

            // Count line
            $line++;

            // Count inserts
            $countInserts++;

            $set = array();
            foreach ($arrRecord as $fieldName => $fieldValue)
            {
                $blnCustomValidation = false;

                // Continue if field is excluded from import
                if (!in_array($fieldName, $this->arrData['selectedFields']))
                {
                    continue;
                }

                // If entries are appended autoincrement id
                if ($this->arrData['importMode'] == 'append_entries' && strtolower($fieldName) == strtolower($this->arrData['primaryKey']))
                {
                    continue;
                }

                if ($fieldValue === null)
                {
                    $fieldValue = '';
                }

                // Convert variable to a string
                $fieldValue = strval($fieldValue);

                // Get the DCA of the current field
                $arrDCA =  &$GLOBALS['TL_DCA'][$strTable]['fields'][$fieldName];
                $arrDCA = is_array($arrDCA) ? $arrDCA : array();

                // Prepare FormWidget object set inputType to "text" if there is no definition
                $inputType = $arrDCA['inputType'] != '' ? $arrDCA['inputType'] : 'text';

                // Map checkboxWizards to regular checkbox widgets
                if ($inputType == 'checkboxWizard')
                {
                    $inputType = 'checkbox';
                }
                $strClass = &$GLOBALS['TL_FFL'][$inputType];

                // HOOK: add custom validation
                $errorMessage = null;
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && is_array($GLOBALS['TL_HOOKS']['importFromCsv']))
                {
                    $arrCustomValidation = array(
                        'strTable'             => $strTable,
                        'arrDCA'               => $arrDCA,
                        'fieldname'            => $fieldName,
                        'value'                => $fieldValue,
                        'arrayLine'            => $arrRecord,
                        'line'                 => $line,
                        'objCsvFile'           => $objCsvFile,
                        'objCsvReader'         => $objCsvReader,
                        'skipWidgetValidation' => false,
                        'hasErrors'            => false,
                        'errorMsg'             => null,
                        'doNotSave'            => false,
                    );

                    $blnCustomValidation = false;
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback)
                    {
                        $arrCustomValidation = System::importStatic($callback[0])->{$callback[1]}($arrCustomValidation, $this);
                        if (!is_array($arrCustomValidation))
                        {
                            throw new \Exception('Expected array as return value.');
                        }
                        $fieldValue = $arrCustomValidation['value'];

                        // Check if widget-validation should be skipped
                        if ($arrCustomValidation['skipWidgetValidation'] === true)
                        {
                            $blnCustomValidation = true;
                        }
                    }

                    if ($arrCustomValidation['errorMsg'] != '')
                    {
                        $fieldValue = $arrCustomValidation['errorMsg'];
                        $doNotSave = true;
                    }

                    if ($arrCustomValidation['doNotSave'])
                    {
                        $doNotSave = true;
                    }
                }

                // Continue if the class does not exist
                // Use form widgets for input validation
                if (class_exists($strClass) && $blnCustomValidation !== true)
                {
                    $objWidget = new $strClass($strClass::getAttributesFromDca($arrDCA, $fieldName, $fieldValue, '', '', $this));
                    $objWidget->storeValues = false;

                    // Set post var, so the content can be validated
                    Input::setPost($fieldName, $fieldValue);

                    // Special treatment for password
                    if ($arrDCA['inputType'] === 'password')
                    {
                        // @see Contao\FormPassword::construct() Line 66
                        $objWidget->useRawRequestData = false;
                        Input::setPost('password_confirm', $fieldValue);
                    }

                    // Add option values in the csv like this: value1||value2||value3
                    if ($arrDCA['eval']['multiple'])
                    {
                        // Convert CSV fields
                        if (isset($arrDCA['eval']['csv']))
                        {
                            if ($fieldValue === '')
                            {
                                $fieldValue = array();
                            }
                            elseif (trim($fieldValue) === '')
                            {
                                $fieldValue = array();
                            }
                            else
                            {
                                $fieldValue = explode($arrDCA['eval']['csv'], $fieldValue);
                            }
                        }
                        elseif (!empty($fieldValue) && is_array(StringUtil::deserialize($fieldValue)))
                        {
                            // The value is serialized array
                            $fieldValue = StringUtil::deserialize($fieldValue);
                        }
                        else
                        {
                            // Add option values in the csv like this: value1||value2||value3
                            $fieldValue = $fieldValue != '' ? explode($strArrayDelimiter, $fieldValue) : array();
                        }

                        Input::setPost($fieldName, $fieldValue);
                        $objWidget->value = $fieldValue;
                    }

                    // !!! SECURITY !!! SKIP VALIDATION FOR SELECTED FIELDS
                    if (!in_array($fieldName, $arrSkipValidationFields))
                    {
                        // Validate input
                        $objWidget->validate();
                    }

                    $fieldValue = $objWidget->value;

                    // Convert date formats into timestamps
                    $rgxp = $arrDCA['eval']['rgxp'];
                    if (($rgxp == 'date' || $rgxp == 'time' || $rgxp == 'datim') && $fieldValue != '' && !$objWidget->hasErrors())
                    {
                        try
                        {
                            $strTimeFormat = $GLOBALS['TL_CONFIG'][$rgxp . 'Format'];
                            $objDate = new Date($fieldValue, $strTimeFormat);
                            $fieldValue = $objDate->tstamp;
                        } catch (\OutOfBoundsException $e)
                        {
                            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $fieldValue));
                        }
                    }

                    // !!! SECURITY !!! SKIP UNIQUE VALIDATION FOR SELECTED FIELDS
                    if (!in_array($fieldName, $arrSkipValidationFields))
                    {
                        // Make sure that unique fields are unique
                        if ($arrDCA['eval']['unique'] && $fieldValue != '' && !Database::getInstance()->isUniqueValue($strTable, $fieldName, $fieldValue, null))
                        {
                            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], $arrDCA['label'][0] ?: $fieldName));
                        }
                    }

                    // Do not save the field if there are errors
                    if ($objWidget->hasErrors())
                    {
                        $doNotSave = true;
                        $fieldValue = sprintf('"%s" => <span class="errMsg">%s</span>', $fieldValue, $objWidget->getErrorsAsString());
                    }
                    else
                    {
                        // Set the correct empty value
                        if ($fieldValue === '')
                        {
                            $fieldValue = $objWidget->getEmptyValue();
                            // Set the correct empty value
                            if ($fieldValue === '')
                            {
                                /**
                                 * Hack Because Contao doesn't handle correct empty string input f.ex username
                                 * @see https://github.com/contao/core-bundle/blob/master/src/Resources/contao/library/Contao/Widget.php#L1526-1527
                                 */
                                if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$fieldName]['sql']))
                                {
                                    $sql = $GLOBALS['TL_DCA'][$strTable]['fields'][$fieldName]['sql'];
                                    if (strpos($sql, 'NOT NULL') === false)
                                    {
                                        if (strpos($sql, 'NULL') !== false)
                                        {
                                            $fieldValue = NULL;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Encode password, if validation was skipped
                if ($arrDCA['inputType'] === 'password')
                {
                    if (strlen($fieldValue))
                    {
                        if ($fieldValue == $arrRecord[$k])
                        {
                            if ($strTable === 'tl_user')
                            {
                                $encoder = System::getContainer()->get('security.encoder_factory')->getEncoder(BackendUser::class);
                            }
                            else
                            {
                                $encoder = System::getContainer()->get('security.encoder_factory')->getEncoder(FrontendUser::class);
                            }
                            $fieldValue = $encoder->encodePassword($fieldValue, null);
                        }
                    }
                }

                // Convert arrays to CSV or serialized strings
                if (is_array($fieldValue))
                {
                    if (isset($arrDCA['eval']['csv']))
                    {
                        $fieldValue = implode($arrDCA['eval']['csv'], $fieldValue);
                    }
                    else
                    {
                        $fieldValue = serialize($fieldValue);
                    }
                }

                // Replace all '[NEWLINE]' tags with the end of line tag
                $set[$fieldName] = str_replace('[NEWLINE]', PHP_EOL, $fieldValue);
            }

            // Insert data record
            if (!$doNotSave)
            {
                // Insert tstamp
                if (Database::getInstance()->fieldExists('tstamp', $strTable))
                {
                    if (!$set['tstamp'] > 0)
                    {
                        $set['tstamp'] = time();
                    }
                }

                // Insert dateAdded (tl_member)
                if (Database::getInstance()->fieldExists('dateAdded', $strTable))
                {
                    if (!$set['dateAdded'] > 0)
                    {
                        $set['dateAdded'] = time();
                    }
                }

                // Add new member to newsletter recipient list
                if ($strTable == 'tl_member' && $set['email'] != '' && $set['newsletter'] != '')
                {
                    foreach (StringUtil::deserialize($set['newsletter'], true) as $newsletterId)
                    {
                        // Check for unique email-address
                        $objRecipient = Database::getInstance()->prepare("SELECT * FROM tl_newsletter_recipients WHERE email=? AND pid=(SELECT pid FROM tl_newsletter_recipients WHERE id=?) AND id!=?")->execute($set['email'], $newsletterId, $newsletterId);

                        if (!$objRecipient->numRows)
                        {
                            $arrRecipient = array();
                            $arrRecipient['tstamp'] = time();
                            $arrRecipient['pid'] = $newsletterId;
                            $arrRecipient['email'] = $set['email'];
                            $arrRecipient['active'] = '1';
                            if ($blnTestMode !== true)
                            {
                                Database::getInstance()->prepare('INSERT INTO tl_newsletter_recipients %s')->set($arrRecipient)->execute();
                            }
                        }
                    }
                }

                try
                {
                    if ($blnTestMode !== true)
                    {
                        // Insert entry into database
                        Database::getInstance()->prepare('INSERT INTO ' . $strTable . ' %s')->set($set)->execute();
                    }
                } catch (\Exception $e)
                {
                    $set['insertError'] = $e->getMessage();
                    $doNotSave = true;
                }
            }

            // Generate html markup for the import report table
            $htmlReport = '';
            $cssClass = 'allOk';
            if ($doNotSave)
            {
                $cssClass = 'error';
                $htmlReport .= sprintf('<tr class="%s"><td class="tdTitle" colspan="2">#%s Datensatz konnte nicht angelegt werden!</td></tr>', $cssClass, $line);

                // Increment error counter if necessary
                $insertError++;
            }
            else
            {
                $htmlReport .= sprintf('<tr class="%s"><td class="tdTitle" colspan="2">#%s Datensatz erfolgreich angelegt!</td></tr>', $cssClass, $line);
            }

            foreach ($set as $k => $v)
            {
                if (is_array($v))
                {
                    $v = serialize($v);
                }
                $htmlReport .= sprintf('<tr class="%s"><td>%s</td><td>%s</td></tr>', $cssClass, StringUtil::substr($k, 30), StringUtil::substrHtml($v, 90));
            }

            $htmlReport .= '<tr class="delim"><td>&nbsp;</td><td>&nbsp;</td></tr>';
            $_SESSION['import_from_csv']['report'][] = $htmlReport;
        }

        $_SESSION['import_from_csv']['status'] = array(
            'blnTestMode' => $blnTestMode ? true : false,
            'rows'        => $countInserts,
            'success'     => $countInserts - $insertError,
            'errors'      => $insertError,
            'offset'      => $offset > 0 ? $offset : '-',
            'limit'       => $limit > 0 ? $limit : '-'
        );
    }

    /**
     * @param $strTable
     * @return mixed|null
     */
    private function getPrimaryKey($strTable)
    {
        $objDb = Database::getInstance()->execute("SHOW INDEX FROM " . $strTable . " WHERE Key_name = 'PRIMARY'");
        if ($objDb->numRows)
        {
            if ($objDb->Column_name != '')
            {
                return $objDb->Column_name;
            }
        }
        return null;
    }
}
