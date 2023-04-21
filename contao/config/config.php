<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

use Markocupic\ImportFromCsvBundle\Contao\Controller\ImportAjaxController;
use Markocupic\ImportFromCsvBundle\Contao\Controller\MountAppAjaxController;
use Markocupic\ImportFromCsvBundle\Contao\Controller\RenderBackendAppController;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;

/*
 * Back end modules
 */
$GLOBALS['BE_MOD']['system']['import_from_csv'] = [
    'tables' => ['tl_import_from_csv'],
    // Add custom controllers
    'renderAppAction' => [RenderBackendAppController::class, 'renderAppAction'],
    'appMountAction' => [MountAppAjaxController::class, 'appMountAction'],
    'importAction' => [ImportAjaxController::class, 'importAction'],
    'javascript' => [
        'bundles/markocupicimportfromcsv/js/vue@3.2.47_global.prod.min.js',
        'bundles/markocupicimportfromcsv/js/importFromCsvApp.js',
    ],
    'stylesheet' => [
        'bundles/markocupicimportfromcsv/css/importFromCsvApp.css',
        'bundles/markocupicimportfromcsv/css/loader.css',
    ],
];

/*
 * Models
 */
$GLOBALS['TL_MODELS']['tl_import_from_csv'] = ImportFromCsvModel::class;
