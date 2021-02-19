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

use Contao\Input;
use Markocupic\ImportFromCsvBundle\Cron\Cron;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;

/**
 * Back end modules
 */
$GLOBALS['BE_MOD']['system']['import_from_csv'] = array(
	'icon'   => 'bundles/markocupicimportfromcsvbundle/file-import-icon-16.png',
	'tables' => array('tl_import_from_csv')
);

if (TL_MODE === 'BE' && Input::get('do') === 'import_from_csv')
{
	$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicimportfromcsv/import_from_csv.js';
	$GLOBALS['TL_CSS'][] = 'bundles/markocupicimportfromcsv/import_from_csv.css';
}

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_import_from_csv'] = ImportFromCsvModel::class;

/**
 * Cronjobs
 */
if (TL_MODE !== 'BE')
{
	$GLOBALS['TL_CRON']['minutely']['importFromCsv'] = array(Cron::class, 'initMinutely');
	$GLOBALS['TL_CRON']['hourly']['importFromCsv']= array(Cron::class, 'initHourly');
	$GLOBALS['TL_CRON']['daily']['importFromCsv'] = array(Cron::class, 'initDaily');
	$GLOBALS['TL_CRON']['weekly']['importFromCsv'] = array(Cron::class, 'initWeekly');
	$GLOBALS['TL_CRON']['monthly']['importFromCsv'] = array(Cron::class, 'initMonthly');
}

/**
 * HOOKS
 */
if (TL_MODE == 'BE' && Input::get('do') === 'import_from_csv')
{
	// disable Hook (example)
	// $GLOBALS['TL_HOOKS']['importFromCsv'][] = array('Markocupic\ImportFromCsv\ImportFromCsvHookExample', 'addGeolocation');
}
