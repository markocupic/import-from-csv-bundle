<?php
/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 * @package import_from_csv
 * @author Marko Cupic 2014, extension sponsered by Rainer-Maria Fritsch - Fast-Doc UG, Berlin
 * @link https://github.com/markocupic/import_from_csv
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

// legends
$GLOBALS['TL_LANG']['tl_import_from_csv']['manual'] = 'Manual/Help';
$GLOBALS['TL_LANG']['tl_import_from_csv']['settings'] = 'Settings';

// fields
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table'] = array('Import data into this table', 'Choose a table for import.');
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode'] = array('Import mode', 'Decide if the table will be truncated before importing the data from the csv-file.');
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure'] = array('Field enclosure', 'Character with which  the field-content is enclosed. Normally it is a double quote: => "');
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator'] = array('Field separator', 'Character with which the fields are separated. Normally it is a semicolon: => ;');
$GLOBALS['TL_LANG']['tl_import_from_csv']['selected_fields'] = array('Select the fields for the import');
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'] = array('Select a csv-file for the import');
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'] = array('File content');

//references
$GLOBALS['TL_LANG']['tl_import_from_csv']['truncate_table'] = array('truncate the target table before importing data');
$GLOBALS['TL_LANG']['tl_import_from_csv']['append_entries'] = array('only append data into the target table');

//buttons
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'][0] = 'Add new import';
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'][1] = 'Add a new import';
$GLOBALS['TL_LANG']['tl_import_from_csv']['launchImportButton'] = 'Launch import process';

// messages
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'] = 'Datarecords';
$GLOBALS['TL_LANG']['tl_import_from_csv']['successful_inserts'] = 'Successful inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['failed_inserts'] = 'Failed inserts';
