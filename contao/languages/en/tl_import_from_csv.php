<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

// Global operations
$GLOBALS['TL_LANG']['tl_import_from_csv']['new'] = ['Add new import', 'Add a new import'];

// Operations
$GLOBALS['TL_LANG']['tl_import_from_csv']['renderAppAction'] = ['Launch import process with ID %s', 'Launch import process with ID %s'];

// Legends
$GLOBALS['TL_LANG']['tl_import_from_csv']['title_legend'] = 'Howto/Help';
$GLOBALS['TL_LANG']['tl_import_from_csv']['docs_legend'] = 'Manual/Help';
$GLOBALS['TL_LANG']['tl_import_from_csv']['settings_legend'] = 'Settings';
$GLOBALS['TL_LANG']['tl_import_from_csv']['limitAndOffset_legend'] = 'Offset and limit (max_execution_time)';
$GLOBALS['TL_LANG']['tl_import_from_csv']['cron_legend'] = 'Cron settings';

// Fields
$GLOBALS['TL_LANG']['tl_import_from_csv']['title'] = ['Title', 'Enter a title please.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['importTable'] = ['Import data into this table', 'Choose a table for import.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['importMode'] = ['Import mode', 'Decide if the table will be truncated before importing the data from the csv-file.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fieldEnclosure'] = ['Field enclosure', 'Character with which  the field-content is enclosed. Normally it is a double quote: => "'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fieldSeparator'] = ['Field separator', 'Character with which the fields are separated. Normally it is a semicolon: => ;'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['selectedFields'] = ['Select the fields for the import', 'Select the fields that you want to import.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'] = ['Select CSV file', 'Select a CSV file for the import.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'] = ['File content', 'Watch the CSV file content.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['skipValidationFields'] = ['Skip validation for these fields', 'Select field that will not be validated.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['offset'] = ['Skip entries', 'Please select the number of entries that will be skipped during the import process.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['limit'] = ['Limit entries', 'Please select the number of entries that will be imported (0 = all).'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['enableCron'] = ['Enable cron', 'Run import as a cronjob.'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['cronLevel'] = ['Cron level', 'Select a cron level'];

// References
$GLOBALS['TL_LANG']['tl_import_from_csv']['truncate_table'] = ['truncate the target table before importing data'];
$GLOBALS['TL_LANG']['tl_import_from_csv']['append_entries'] = ['only append data into the target table'];

// Buttons
$GLOBALS['TL_LANG']['tl_import_from_csv']['runImportBtn'] = 'Launch import process';
$GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportBtn'] = 'Launch  import in test mode';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showErrorsBtn'] = 'Show failed inserts only';
$GLOBALS['TL_LANG']['tl_import_from_csv']['showAllButton'] = 'Show all inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['btnImport'] = 'Start import process';
$GLOBALS['TL_LANG']['tl_import_from_csv']['btnImportTest'] = 'Test import';
$GLOBALS['TL_LANG']['tl_import_from_csv']['editItemTitle'] = 'edit import';

// Messages
$GLOBALS['TL_LANG']['tl_import_from_csv']['data_records'] = 'Datarecords';
$GLOBALS['TL_LANG']['tl_import_from_csv']['successful_inserts'] = 'Successful inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['failed_inserts'] = 'Failed inserts';
$GLOBALS['TL_LANG']['tl_import_from_csv']['info_text'] = '<span>An introduction and many tips can be found on the <a href="https://github.com/markocupic/import-from-csv-bundle">project website</a>.</span>';
$GLOBALS['TL_LANG']['tl_import_from_csv']['data_record_insert_failed'] = 'Insert failed!';
$GLOBALS['TL_LANG']['tl_import_from_csv']['data_record_insert_succeed'] = 'Insert succeed!';
$GLOBALS['TL_LANG']['tl_import_from_csv']['confirm_start_import'] = 'Do you really want to start the import process?';
$GLOBALS['TL_LANG']['tl_import_from_csv']['exception_message'] = 'An unexpected error occurred during the import process. Please switch to the Contao Debug-Mode to find out more.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_process_completed'] = 'Import process successfully completed. You can close the window now.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_process_completed_with_errors'] = 'Import process completed with one or more errors. You can find information about the cause of the error in the error log.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['import_process_started'] = 'Import process started. Please do not close this window until the import has succeeded.';
$GLOBALS['TL_LANG']['tl_import_from_csv']['loading_application'] = 'loading application';
