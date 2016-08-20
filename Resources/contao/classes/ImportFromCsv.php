<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * @package import_from_csv
 * @author Marko Cupic 2014, extension sponsered by Rainer-Maria Fritsch - Fast-Doc UG, Berlin
 * @link https://github.com/markocupic/import_from_csv
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace MCupic\ImportFromCsv;

/**
 * Class ImportFromCsv
 * Copyright: 2014 Marko Cupic Sponsor der Erweiterung: Fast-Doc UG, Berlin
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
     * @param string $strPrimaryKey
     * @param string $arrDelim
     */
    public function importCsv(\File $objCsvFile, $strTable, $strImportMode, $arrSelectedFields = null, $strFieldseparator = ';', $strFieldenclosure = '', $strPrimaryKey = 'id', $arrDelim = '||')
    {
        // store sucess or failure message in the session
        $_SESSION['import_from_csv']['report'] = array();

        // load language file
        \System::loadLanguageFile($strTable);

        // load dca
        $this->loadDataContainer($strTable);

        // store the options in $this->arrData
        $this->arrData = array(
            'tablename' => $strTable, 'primaryKey' => $strPrimaryKey, 'importMode' => $strImportMode,
            'selectedFields' => is_array($arrSelectedFields) ? $arrSelectedFields : array(),
            'fieldSeparator' => $strFieldseparator, 'fieldEnclosure' => $strFieldenclosure,
        );

        // truncate table
        if ($this->arrData['importMode'] == 'truncate_table')
        {
            $this->Database->execute('TRUNCATE TABLE `' . $strTable . '`');
        }

        if (count($this->arrData['selectedFields']) < 1)
        {
            return;
        }

        // get content as array
        $arrFileContent = $objCsvFile->getContentAsArray();
        $arrFieldnames = explode($this->arrData['fieldSeparator'], $arrFileContent[0]);

        // trim quotes in the first line and get the fieldnames
        $arrFieldnames = array_map(array($this, 'myTrim'), $arrFieldnames);

        // count rows
        $rows = 0;

        // count errors
        $insertError = 0;

        // store each line as an entry in the db
        foreach ($arrFileContent as $line => $lineContent)
        {
            $doNotSave = false;

            // line 0 contains the fieldnames
            if ($line == 0)
            {
                continue;
            }

            // count rows
            $rows++;

            // separate the line into the different fields
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

                // continue if field is excluded from import
                if (!in_array($fieldname, $this->arrData['selectedFields']))
                {
                    continue;
                }

                // if entries are appended autoincrement id
                if ($this->arrData['importMode'] == 'append_entries' && strtolower($fieldname) == $this->arrData['primaryKey'])
                {
                    continue;
                }

                // get the field content
                $fieldValue = $arrLine[$k];


                // trim quotes
                $fieldValue = $this->myTrim($fieldValue);

                // convert variable to a string (see #2)
                $fieldValue = strval($fieldValue);

                // get the DCA of the current field
                $arrDCA =  &$GLOBALS['TL_DCA'][$strTable]['fields'][$fieldname];
                $arrDCA = is_array($arrDCA) ? $arrDCA : array();

                // Prepare FormWidget object !set inputType to "text" if there is no definition
                $inputType = $arrDCA['inputType'] != '' ? $arrDCA['inputType'] : 'text';


                // Map checkboxWizards to regular checkbox widgets
                if ($inputType == 'checkboxWizard')
                {
                    $inputType = 'checkbox';
                }
                $strClass = &$GLOBALS['TL_FFL'][$inputType];

                // HOOK: add custom validation
                if (isset($GLOBALS['TL_HOOKS']['importFromCsv']) && is_array($GLOBALS['TL_HOOKS']['importFromCsv']))
                {
                    $arrCustomValidation = array(
                        'strTable' => $strTable,
                        'arrDCA' => $arrDCA,
                        'fieldname' => $fieldname,
                        'value' => $fieldValue,
                        'arrayLine' => $assocArrayLine,
                        'line' => $line,
                        'objCsvFile' => $objCsvFile,
                        'skipWidgetValidation' => false,
                        'hasErrors' => false,
                        'errorMsg' => null,
                        'doNotSave' => false,
                    );

                    $blnCustomValidation = false;
                    foreach ($GLOBALS['TL_HOOKS']['importFromCsv'] as $callback)
                    {
                        $this->import($callback[0]);
                        $arrCustomValidation = $this->{$callback[0]}->{$callback[1]}($arrCustomValidation, $this);
                        if (!is_array($arrCustomValidation))
                        {
                            die('Als Rückgabewert wird ein Array erwartet. Fehler in ' . __FILE__ . ' in Zeile ' . __LINE__ . '.');
                        }
                        $fieldValue = $arrCustomValidation['value'];

                        // Check if widget-validation should be skipped
                        if ($blnCustomValidation['skipWidgetValidation'] === true)
                        {
                            $blnCustomValidation = true;
                        }
                    }

                    if ($arrCustomValidation['errorMsg'] != '')
                    {
                        $fieldValue = sprintf('<span class="errMsg">%s</span>', $arrCustomValidation['errorMsg']);
                    }

                    if ($arrCustomValidation['doNotSave'])
                    {
                        $doNotSave = true;
                    }
                }

                // Continue if the class does not exist
                // Use form widgets for input validation
                if (class_exists($strClass) && $blnCustomValidation === false)
                {
                    $objWidget = new $strClass($strClass::getAttributesFromDca($arrDCA, $fieldname, $fieldValue, '', '', $this));
                    $objWidget->storeValues = false;

                    // Set post var, so the content can be validated
                    \Input::setPost($fieldname, $fieldValue);
                    if ($fieldname == 'password')
                    {
                        \Input::setPost('password_confirm', $fieldValue);
                    }

                    // add option values in the csv like this: value1||value2||value3
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

                    // validate input
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


            // insert data record
            if (!$doNotSave)
            {
                // insert tstamp
                if ($this->Database->fieldExists('tstamp', $strTable))
                {
                    if (!$set['tstamp'] > 0)
                    {
                        $set['tstamp'] = time();
                    }
                }

                // insert dateAdded (tl_member)
                if ($this->Database->fieldExists('dateAdded', $strTable))
                {
                    if (!$set['dateAdded'] > 0)
                    {
                        $set['dateAdded'] = time();
                    }
                }

                // add new member to newsletter recipient list
                if ($strTable == 'tl_member' && $set['email'] != '' && $set['newsletter'] != '')
                {
                    foreach (deserialize($set['newsletter'], true) as $newsletterId)
                    {
                        // check for unique email-address
                        $objRecipient = $this->Database->prepare("SELECT * FROM tl_newsletter_recipients WHERE email=? AND pid=(SELECT pid FROM tl_newsletter_recipients WHERE id=?) AND id!=?")
                            ->execute($set['email'], $newsletterId, $newsletterId);

                        if (!$objRecipient->numRows)
                        {
                            $arrRecipient = array();
                            $arrRecipient['tstamp'] = time();
                            $arrRecipient['pid'] = $newsletterId;
                            $arrRecipient['email'] = $set['email'];
                            $arrRecipient['active'] = '1';
                            $this->Database->prepare('INSERT INTO tl_newsletter_recipients %s')->set($arrRecipient)->execute();
                        }
                    }
                }

                try
                {
                    // insert entry into database
                    $this->Database->prepare('INSERT INTO ' . $strTable . ' %s')->set($set)->execute();
                } catch (\Exception $e)
                {
                    $set['insertError'] = $e->getMessage();
                    $doNotSave = true;
                }
            }

            // generate html markup for the import report table
            $htmlReport = '';
            $cssClass = 'allOk';
            if ($doNotSave)
            {
                $cssClass = 'error';
                $htmlReport .= sprintf('<tr class="%s"><td class="tdTitle" colspan="2">#%s Datensatz konnte nicht angelegt werden!</td></tr>', $cssClass, $line);

                // increment error counter if necessary
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
            'rows' => $rows,
            'success' => $rows - $insertError,
            'errors' => $insertError
        );
    }


    /**
     * @param string
     * @return string
     */
    private function myTrim($strFieldname)
    {
        return trim($strFieldname, $this->arrData['fieldEnclosure']);
    }
}
