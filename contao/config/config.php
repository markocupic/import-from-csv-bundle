<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

use Contao\System;
use Markocupic\ImportFromCsvBundle\Controller\Backend\ImportAjaxController;
use Markocupic\ImportFromCsvBundle\Controller\Backend\MountAppAjaxController;
use Markocupic\ImportFromCsvBundle\Controller\Backend\RenderBackendAppController;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
/*
 * Back end modules
 */
$GLOBALS['BE_MOD']['system']['import_from_csv'] = [
    'tables'          => ['tl_import_from_csv'],
    // Add custom controllers
    'renderAppAction' => [RenderBackendAppController::class, 'renderAppAction'],
    'appMountAction'  => [MountAppAjaxController::class, 'appMountAction'],
    'importAction'    => [ImportAjaxController::class, 'importAction'],
    'javascript'      => [
        System::getContainer()->get('assets.packages')->getUrl('js/vue/dist/vue.global.prod.js', 'markocupic_import_from_csv'),
        System::getContainer()->get('assets.packages')->getUrl('js/import_from_csv_app.js', 'markocupic_import_from_csv'),
    ],
    'stylesheet'      => [
        System::getContainer()->get('assets.packages')->getUrl('css/import_from_csv_app.css', 'markocupic_import_from_csv'),
        System::getContainer()->get('assets.packages')->getUrl('css/loader.css', 'markocupic_import_from_csv'),
    ],
];

/*
 * Models
 */
$GLOBALS['TL_MODELS']['tl_import_from_csv'] = ImportFromCsvModel::class;
