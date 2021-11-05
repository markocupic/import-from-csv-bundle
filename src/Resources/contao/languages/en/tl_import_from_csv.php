<?php

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

// Keys
$GLOBALS['TL_LANG']['tl_import_from_csv']['csvImport'] = ['Launch import with ID %s', 'Launch import with ID %s'];

// Global operations
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'] = array('Add new import', 'Add a new import');

// Legends
$GLOBALS['TL_LANG']['tl_import_from_csv']['manual'] = 'Manual/Help';
$GLOBALS['TL_LANG']['tl_import_from_csv']['settings'] = 'Settings';
$GLOBALS['TL_LANG']['tl_import_from_csv']['limitAndOffset_settings'] = "Offset and limit (max_execution_time)";
$GLOBALS['TL_LANG']['tl_import_from_csv']['cron_settings'] = "Cron settings";

// Fields
$GLOBALS['TL_LANG']['tl_import_from_csv']['title'] = ["Title", "Enter a title please."];
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table'] = array('Import data into this table', 'Choose a table for import.');
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode'] = array('Import mode', 'Decide if the table will be truncated before importing the data from the csv-file.');
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure'] = array('Field enclosure', 'Character with which  the field-content is enclosed. Normally it is a double quote: => "');
$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator'] = array('Field separator', 'Character with which the fields are separated. Normally it is a semicolon: => ;');
$GLOBALS['TL_LANG']['tl_import_from_csv']['selected_fields'] = array('Select the fields for the import');
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'] = array('Select a csv-file for the import');
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'] = array('File content');
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'] = array("Skip validation for these fields", "Select field that will not be validated.");
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'] = array("Skip entries", "Please select the number of entries that will be skiped during the import process.");
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'] = array("Limit entries", "Please select the number of entries that will be imported (0 = all).");
$GLOBALS['TL_LANG']['tl_import_from_csv']['enableCron'] = array("Enable cron", "Run import as a cronjob.");
$GLOBALS['TL_LANG']['tl_import_from_csv']['cronLevel'] = array("Cron level", "Select a cron level");

// References
$GLOBALS['TL_LANG']['tl_import_from_csv']['truncate_table'] = array('truncate the target table before importing data');
$GLOBALS['TL_LANG']['tl_import_from_csv']['append_entries'] = array('only append data into the target table');

// Buttons
$GLOBALS['TL_LANG']['tl_import_from_csv']['runImportBtn'] = 'Launch import process';
$GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportBtn'] = 'Launch  import in test mode';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showErrorsBtn'] = 'Show failed inserts only';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showAllButton'] = 'Show all inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['btnImport'] = 'Start import process';
$GLOBALS['TL_LANG']['tl_import_from_csv']['btnImportTest'] = 'Test import';
$GLOBALS['TL_LANG']['tl_import_from_csv']['editItemTitle'] = 'edit import';

// Messages
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'] = 'Datarecords';
$GLOBALS['TL_LANG']['tl_import_from_csv']['successfullInserts'] = 'Successful inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['failedInserts'] = 'Failed inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['infoText'] = '<span>An introduction and many tips can be found on the <a href="https://github.com/markocupic/import-from-csv-bundle">project website</a>.</span>';
$GLOBALS['TL_LANG']['tl_import_from_csv']['importOverview'] = "Import overview";
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecordInsertFailed'] = "Insert failed!";
$GLOBALS['TL_LANG']['tl_import_from_csv']['datarecordInsertSucceed'] = "Insert succeed!";
$GLOBALS['TL_LANG']['tl_import_from_csv']['confirmStartImport'] = "Do you really want to start the import process?";
$GLOBALS['TL_LANG']['tl_import_from_csv']['exceptionMsg'] = 'An unexpected error occurred during the import process. Please switch to the Contao Debug-Mode to find out more.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['importProcessCompleted'] = 'Import process completed. You can close the window now.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['importProcessStarted'] = 'Import process started. Please do not close this window until the import has succeeded.';
