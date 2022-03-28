<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

$GLOBALS['TL_DCA']['tl_import_from_csv'] = [
    'config'      => [
        'dataContainer'    => 'Table',
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list'        => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['importTable ASC'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields' => [
                'title',
                'importTable',
            ],
            'format' => '%s [%s]',
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'            => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy'            => [
                'href' => 'act=copy',
                'icon' => 'copy.svg',
            ],
            'delete'          => [
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'show'            => [
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
            'renderAppAction' => [
                'href' => 'key=renderAppAction',
                'icon' => 'bundles/markocupicimportfromcsv/import.svg',
            ],
        ],
    ],
    'palettes'    => [
        'default'      => '
            {title_legend},title;
            {docs_legend},explanation;
            {settings_legend},importTable,selectedFields,fieldSeparator,fieldEnclosure,importMode,fileSRC,listLines,skipValidationFields;
            {limitAndOffset_legend},offset,limit;
            {cron_legend},enableCron
        ',
        '__selector__' => ['enableCron'],
    ],
    'subpalettes' => [
        'enableCron' => 'cronLevel',
    ],
    'fields'      => [
        'id'                   => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'title'                => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory'      => true,
                'decodeEntities' => true,
                'maxlength'      => 255,
                'tl_class'       => 'w50',
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'tstamp'               => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'explanation'          => [
            'eval' => [
                'tl_class'  => 'clr',
                'doNotShow' => true,
            ],
        ],
        'importTable'          => [
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'eval'      => [
                'multiple'           => false,
                'mandatory'          => true,
                'includeBlankOption' => true,
                'submitOnChange'     => true,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'fieldSeparator'       => [
            'inputType' => 'text',
            'default'   => ';',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 1,
            ],
            'sql'       => "varchar(255) NOT NULL default ';'",
        ],
        'fieldEnclosure'       => [
            'inputType' => 'text',
            'eval'      => [
                'mandatory'         => false,
                'useRawRequestData' => true,
                'maxlength'         => 1,
            ],
            'sql'       => "varchar(255) NOT NULL default '\"'",
        ],
        'importMode'           => [
            'inputType' => 'select',
            'options'   => [
                'append_entries',
                'truncate_table',
            ],
            'reference' => &$GLOBALS['TL_LANG']['tl_import_from_csv'],
            'eval'      => [
                'multiple'  => false,
                'mandatory' => true,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'selectedFields'       => [
            'inputType' => 'checkbox',
            'eval'      => [
                'multiple'  => true,
                'mandatory' => true,
            ],
            'sql'       => 'blob NULL',
        ],
        'skipValidationFields' => [
            'inputType' => 'select',
            'eval'      => [
                'multiple'  => true,
                'chosen'    => true,
                'mandatory' => false,
            ],
            'sql'       => 'blob NULL',
        ],
        'fileSRC'              => [
            'inputType' => 'fileTree',
            'eval'      => [
                'multiple'       => false,
                'fieldType'      => 'radio',
                'files'          => true,
                'filesOnly'      => true,
                'mandatory'      => true,
                'extensions'     => 'csv',
                'submitOnChange' => true,
            ],
            'sql'       => 'binary(16) NULL',
        ],
        'listLines'            => [
            'eval' => [
                'tl_class'  => 'clr',
                'doNotShow' => true,
            ],
        ],
        'offset'               => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'rgxp'     => 'natural',
                'tl_class' => 'w50',
            ],
            'sql'       => 'smallint(5) unsigned NOT NULL default 0',
        ],
        'limit'                => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'rgxp'     => 'natural',
                'tl_class' => 'w50',
            ],
            'sql'       => 'smallint(5) unsigned NOT NULL default 0',
        ],
        'enableCron'           => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => [
                'submitOnChange' => true,
                'tl_class'       => 'clr',
            ],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'cronLevel'            => [
            'exclude'   => true,
            'inputType' => 'select',
            'options'   => [
                'minutely',
                'daily',
                'hourly',
                'weekly',
                'monthly',
                'yearly',
            ],
            'eval'      => [
                'tl_class' => 'w50',
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
    ],
];
