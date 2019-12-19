<?php

/**
 * Import from csv bundle: Backend module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package import-from-csv-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Back end modules
 */

$GLOBALS['BE_MOD']['system']['import_from_csv'] = array(
    'icon'   => 'bundles/markocupicimportfromcsvbundle/file-import-icon-16.png',
    'tables' => array('tl_import_from_csv')
);

if (TL_MODE == 'BE' && $_GET['do'] == 'import_from_csv')
{
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicimportfromcsv/import_from_csv.js';
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicimportfromcsv/import_from_csv.css';
}

/**
 * HOOKS
 */
if (TL_MODE == 'BE' && \Input::get('do') == 'import_from_csv')
{
    // disable Hook (example)
    // $GLOBALS['TL_HOOKS']['importFromCsv'][] = array('Markocupic\ImportFromCsv\ImportFromCsvHookExample', 'addGeolocation');
}

