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

use Markocupic\ImportFromCsvBundle\Contao\Controller\AccessTokenProviderController;
use Markocupic\ImportFromCsvBundle\Contao\Controller\MountAppAjaxController;
use Markocupic\ImportFromCsvBundle\Contao\Controller\ImportAjaxController;
use Markocupic\ImportFromCsvBundle\Contao\Controller\RenderBackendAppController;
use Markocupic\ImportFromCsvBundle\Cron\Cron;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;

/**
 * Back end modules
 */
$GLOBALS['BE_MOD']['system']['import_from_csv'] = array(
    'tables'          => array('tl_import_from_csv'),
    // Add custom controllers
    'renderAppAction' => array(RenderBackendAppController::class, 'renderAppAction'),
    'appMountAction'  => array(MountAppAjaxController::class, 'appMountAction'),
    'importAction'    => array(ImportAjaxController::class, 'importAction'),
    'javascript'      => [
        'bundles/markocupicimportfromcsv/js/vue@2.6.14.js',
        'bundles/markocupicimportfromcsv/js/importFromCsvApp.js',
    ],
    'stylesheet'      => [
        'bundles/markocupicimportfromcsv/css/importFromCsvApp.css',
        'bundles/markocupicimportfromcsv/css/loader.css'
    ],
);

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_import_from_csv'] = ImportFromCsvModel::class;

/**
 * Cronjobs
 */
if (TL_MODE !== 'BE') {
    $GLOBALS['TL_CRON']['minutely']['importFromCsv'] = array(Cron::class, 'initMinutely');
    $GLOBALS['TL_CRON']['hourly']['importFromCsv'] = array(Cron::class, 'initHourly');
    $GLOBALS['TL_CRON']['daily']['importFromCsv'] = array(Cron::class, 'initDaily');
    $GLOBALS['TL_CRON']['weekly']['importFromCsv'] = array(Cron::class, 'initWeekly');
    $GLOBALS['TL_CRON']['monthly']['importFromCsv'] = array(Cron::class, 'initMonthly');
}
