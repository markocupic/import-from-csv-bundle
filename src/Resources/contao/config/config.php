<?php
/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 * @package import_from_csv
 * @author Marko Cupic 2014, extension sponsered by Rainer-Maria Fritsch - Fast-Doc UG, Berlin
 * @link https://github.com/markocupic/import_from_csv
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Back end modules
 */
if (TL_MODE == 'BE')
{
    $GLOBALS['BE_MOD']['system']['import_from_csv'] = array(
        'icon'   => 'system/modules/import_from_csv/assets/file-import-icon-16.png',
        'tables' => array('tl_import_from_csv')
    );
}


if (TL_MODE == 'BE' && $_GET['do'] == 'import_from_csv')
{
    $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/import_from_csv/assets/import_from_csv.js';
    $GLOBALS['TL_CSS'][] = 'system/modules/import_from_csv/assets/import_from_csv.css';
    $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = array('tl_import_from_csv', 'parseBackendTemplate');
}
/**
 * HOOKS
 */
if (TL_MODE == 'BE' && \Input::get('do') == 'import_from_csv')
{
    // disable Hook (example)
    // $GLOBALS['TL_HOOKS']['importFromCsv'][] = array('MCupic\ImportFromCsv\ImportFromCsvHookExample', 'addGeolocation');
}

