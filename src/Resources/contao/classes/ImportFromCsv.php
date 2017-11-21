<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * @package import_from_csv
 * @author Marko Cupic 2017
 * @link https://github.com/markocupic/import-from-csv-bundle
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Markocupic\ImportFromCsv;

/**
 * Class ImportFromCsv
 * Copyright: 2017 Marko Cupic
 *
 * @author Marko Cupic <m.cupic@gmx.ch>
 * @package import_from_csv
 */


class ImportFromCsv extends \Backend
{

    /**
     * array
     * import options
     */
    public $arrData;


    /**
     * @param \File $objCsvFile
     * @param $strTable
     * @param $strImportMode
     * @param null $arrSelectedFields
     * @param string $strFieldseparator
     * @param string $strFieldenclosure
     * @param string $arrDelim
     * @throws \Exception
     */
    public function importCsv(\File $objCsvFile, $strTable, $strImportMode, $arrSelectedFields = null, $strFieldseparator = ';', $strFieldenclosure = '', $arrDelim = '||', $blnTestMode = false)
    {
        // Get the primary key
        $strPrimaryKey = $this->getPrimaryKey($strTable);
        if ($strPrimaryKey === null)
        {
            throw new \Exception('No primary key found in ' . $strTable);
        }

        // Store sucess or failure message in the session
        $_SESSION['import_from_csv']['report'] = array();

        // Load language file
        \System::loadLanguageFile($strTable);

        // Load dca
        $this->loadDataContainer($strTable);

        // Store the options in $this->arrData
        $this->arrData = array(
            'tablename'      => $strTable,
            'primaryKey'     => $strPrimaryKey,
            'importMode'     => $strImportMode,
            'selectedFields' => is_array($arrSelectedFields) ? $arrSelectedFields : array(),
            'fieldSeparator' => $strFieldseparator,
            'fieldEnclosure' => $strFieldenclosure,
        );

        // Truncate table
        if ($this->arrData['importMode'] == 'truncate_table')
        {
            $this->Database->execute('TRUNCATE TABLE `' . $strTable . '`');
        }

        if (count($this->arrData['selectedFields']) < 1)
        {
            return;
        }

        // Auto detect line endings https://stackoverflow.com/questions/31331110/auto-detect-line-endings-are-there-side-effects
        ini_set("auto_detect_line_endings", true);

        // Get content as array
        $arrFileContent = $objCsvFile->getContentAsArray();
        $arrFieldnames = explode($this->arrData['fieldSeparator'], $arrFileContent[0]);

        // Trim quotes in the first line and get the fieldnames
        $arrFieldnames = array_map(function ($strFieldname)
        {
            return trim($strFieldname, $this->arrData['fieldEnclosure']);
        }, $arrFieldnames);

        // Count rows
        $rows = 0;

        // Count errors
        $insertError = 0;

        // Store each line as an entry in the db
        foreach ($arrFileContent as $line => $lineContent)
        {
            $doNotSave = false;

            // Line 0 contains the fieldnames
            if ($line == 0)
            {
                continue;
            }

            // Count rows
            $rows++;

            // Separate the line into the different fields
            $arrLine = explode($this->arrData['fieldSeparator'], $lineContent);

            // Set the associative Array with the line content
            $assocArrayLine = array();
            foreach ($arrFieldnames as $k => $fieldname)
            {
                $assocArrayLine[$fieldname] = $arrLine[$k];
            }

            $set = array();
            foreach ($arrFieldnames as $k => $fieldname)
            {

                $blnCustomValidation = false;

                // Continue if field is excluded from import
                if (!in_array($fieldname, $this->arrData['selectedFields']))
                {
                    continue;
                }

                // If entries are appended autoincrement id
                if ($this->arrData['importMode'] == 'append_entries' && strtolower($fieldname) == strtolower($this->arrData['primaryKey']))
                {
                    continue;
                }

                // Get the field content
                $fieldValue = $arrLine[$k];

                // Trim quotes
                $fieldValue = trim($fieldValue, $this->arrData['fieldEnclosure']);

                // Convert variable to a string
                $fieldValue = strval($fieldValue);

                // Get the DCA of the current field
                $arrDCA =  &$GLOBALS['TL_DCA'][$strTable]['fields'][$fieldname];
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
                        'fieldname'            => $fieldname,
                        'value'                => $fieldValue,
                        'arrayLine'            => $assocArrayLine,
                        'line'                 => $line,
                        'objCsvFile'           => $objCsvFile,
                        'skipWidgetValidation' => false,
                        'hasErrors'            => false,
                        'errorMsg'             => null,
                        'doNotSave'            => false,
                    );

                    $blnCustomValidation = false;
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback)
                    {
                        $this->import($callback[0]);
                        $arrCustomValidation = $this->{$callback[0]}->{$callback[1]}($arrCustomValidation, $this);
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
                    $objWidget = new $strClass($strClass::getAttributesFromDca($arrDCA, $fieldname, $fieldValue, '', '', $this));
                    $objWidget->storeValues = false;

                    // Set post var, so the content can be validated
                    \Input::setPost($fieldname, $fieldValue);

                    // Special treatment for password
                    if ($objWidget instanceof \FormPassword)
                    {
                        // @see Contao\FormPassword::construct() Line 66
                        $objWidget->useRawRequestData = false;
                        \Input::setPost('password_confirm', $fieldValue);
                    }

                    // Add option values in the csv like this: value1||value2||value3
                    if ($inputType == 'radio' || $inputType == 'checkbox' || $inputType == 'select')
                    {
                        if ($arrDCA['eval']['multiple'] === true)
                        {
                            // Security issues in Contao #6695
                            if (version_compare(VERSION . BUILD, '3.2.5', '>='))
                            {
                                $fieldValue = $fieldValue != '' ? explode($arrDelim, $fieldValue) : null;
                            }
                            else
                            {
                                $fieldValue = $fieldValue != '' ? serialize(explode($arrDelim, $fieldValue)) : null;
                            }

                            \Input::setPost($fieldname, $fieldValue);
                            $objWidget->value = $fieldValue;
                        }
                    }

                    // Validate input
                    $objWidget->validate();


                    $fieldValue = $objWidget->value;

                    // Convert date formats into timestamps
                    $rgxp = $arrDCA['eval']['rgxp'];
                    if (($rgxp == 'date' || $rgxp == 'time' || $rgxp == 'datim') && $fieldValue != '' && !$objWidget->hasErrors())
                    {
                        try
                        {
                            $strTimeFormat = $GLOBALS['TL_CONFIG'][$rgxp . 'Format'];
                            $objDate = new \Date($fieldValue, $strTimeFormat);
                            $fieldValue = $objDate->tstamp;
                        } catch (\OutOfBoundsException $e)
                        {
                            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $fieldValue));
                        }
                    }

                    // Make sure that unique fields are unique
                    if ($arrDCA['eval']['unique'] && $fieldValue != '' && !$this->Database->isUniqueValue($strTable, $fieldname, $fieldValue, null))
                    {
                        $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], $arrDCA['label'][0] ?: $fieldname));
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
                        }
                    }
                }

                $set[$fieldname] = is_array($fieldValue) ? serialize($fieldValue) : $fieldValue;
            }


            // Insert data record
            if (!$doNotSave)
            {
                // Insert tstamp
                if ($this->Database->fieldExists('tstamp', $strTable))
                {
                    if (!$set['tstamp'] > 0)
                    {
                        $set['tstamp'] = time();
                    }
                }

                // Insert dateAdded (tl_member)
                if ($this->Database->fieldExists('dateAdded', $strTable))
                {
                    if (!$set['dateAdded'] > 0)
                    {
                        $set['dateAdded'] = time();
                    }
                }

                // Add new member to newsletter recipient list
                if ($strTable == 'tl_member' && $set['email'] != '' && $set['newsletter'] != '')
                {
                    foreach (deserialize($set['newsletter'], true) as $newsletterId)
                    {
                        // Check for unique email-address
                        $objRecipient = $this->Database->prepare("SELECT * FROM tl_newsletter_recipients WHERE email=? AND pid=(SELECT pid FROM tl_newsletter_recipients WHERE id=?) AND id!=?")->execute($set['email'], $newsletterId, $newsletterId);

                        if (!$objRecipient->numRows)
                        {
                            $arrRecipient = array();
                            $arrRecipient['tstamp'] = time();
                            $arrRecipient['pid'] = $newsletterId;
                            $arrRecipient['email'] = $set['email'];
                            $arrRecipient['active'] = '1';
                            if ($blnTestMode !== true)
                            {
                                $this->Database->prepare('INSERT INTO tl_newsletter_recipients %s')->set($arrRecipient)->execute();
                            }
                        }
                    }
                }

                try
                {
                    if ($blnTestMode !== true)
                    {
                        // Insert entry into database
                        $this->Database->prepare('INSERT INTO ' . $strTable . ' %s')->set($set)->execute();
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
                $htmlReport .= sprintf('<tr class="%s"><td>%s</td><td>%s</td></tr>', $cssClass, \StringUtil::substr($k, 30), \StringUtil::substrHtml($v, 90));
            }


            $htmlReport .= '<tr class="delim"><td>&nbsp;</td><td>&nbsp;</td></tr>';
            $_SESSION['import_from_csv']['report'][] = $htmlReport;
        }

        $_SESSION['import_from_csv']['status'] = array(
            'blnTestMode' => $blnTestMode ? true : false,
            'rows'        => $rows,
            'success'     => $rows - $insertError,
            'errors'      => $insertError,
        );
    }


    /**
     * @param $strTable
     * @return mixed|null
     */
    private function getPrimaryKey($strTable)
    {
        $objDb = \Database::getInstance()->execute("SHOW INDEX FROM " . $strTable . " WHERE Key_name = 'PRIMARY'");
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