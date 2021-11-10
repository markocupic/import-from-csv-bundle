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

$GLOBALS['TL_DCA']['tl_import_from_csv'] = array(
	'config'      => array(
		'dataContainer' => 'Table',
		'sql'           => array(
			'keys' => array(
				'id' => 'primary',
			),
		),
	),
	'list'        => array(
		'sorting'           => array(
			'fields' => array('tstamp DESC'),
		),
		'label'             => array(
			'fields' => array(
				'title',
				'importTable',
			),
			'format' => '%s [%s]',
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
		),
		'operations'        => array(
			'edit'            => array(
				'href' => 'act=edit',
				'icon' => 'edit.gif',
			),
			'copy'            => array
			(
				'href' => 'act=copy',
				'icon' => 'copy.svg',
			),
			'delete'          => array(
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
			),
			'show'            => array(
				'href' => 'act=show',
				'icon' => 'show.gif',
			),
			'renderAppAction' => array(
				'href' => 'key=renderAppAction',
				'icon' => 'bundles/markocupicimportfromcsv/import.svg',
			),
		),
	),
	'palettes'    => array(
		'default'      => '
            {title_legend},title;
            {docs_legend},explanation;
            {settings_legend},importTable,selectedFields,fieldSeparator,fieldEnclosure,importMode,fileSRC,listLines,skipValidationFields;
            {limitAndOffset_legend},offset,limit;
            {cron_legend},enableCron
        ',
		'__selector__' => array('enableCron'),
	),
	'subpalettes' => array(
		'enableCron' => 'cronLevel',
	),
	'fields'      => array(
		'id'                   => array(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'title'                => array(
			'exclude'   => true,
			'inputType' => 'text',
			'search'    => true,
			'eval'      => array(
				'mandatory'      => true,
				'decodeEntities' => true,
				'maxlength'      => 255,
				'tl_class'       => 'w50',
			),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'tstamp'               => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'explanation'          => array(
			'eval' => array(
				'tl_class'  => 'clr',
				'doNotShow' => true,
			),
		),
		'importTable'          => array(
			'inputType' => 'select',
			'eval'      => array(
				'multiple'           => false,
				'mandatory'          => true,
				'includeBlankOption' => true,
				'submitOnChange'     => true,
			),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'fieldSeparator'       => array(
			'inputType' => 'text',
			'default'   => ';',
			'eval'      => array(
				'mandatory' => true,
				'maxlength' => 1,
			),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'fieldEnclosure'       => array(
			'inputType' => 'text',
			'eval'      => array(
				'mandatory'         => false,
				'useRawRequestData' => true,
				'maxlength'         => 1,
			),
			'sql'       => "varchar(255) NOT NULL default '\"'",
		),
		'importMode'           => array(
			'inputType' => 'select',
			'options'   => array(
				'append_entries',
				'truncate_table',
			),
			'reference' => $GLOBALS['TL_LANG']['tl_import_from_csv'],
			'eval'      => array(
				'multiple'  => false,
				'mandatory' => true,
			),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'selectedFields'       => array(
			'inputType' => 'checkbox',
			'eval'      => array(
				'multiple'  => true,
				'mandatory' => true,
			),
			'sql'       => "blob NULL",
		),
		'skipValidationFields' => array(
			'inputType' => 'select',
			'eval'      => array(
				'multiple'  => true,
				'chosen'    => true,
				'mandatory' => false,
			),
			'sql'       => "blob NULL",
		),
		'fileSRC'              => array(
			'inputType' => 'fileTree',
			'eval'      => array(
				'multiple'       => false,
				'fieldType'      => 'radio',
				'files'          => true,
				'filesOnly'      => true,
				'mandatory'      => true,
				'extensions'     => 'csv',
				'submitOnChange' => true,
			),
			'sql'       => "binary(16) NULL",
		),
		'listLines'            => array(
			'eval' => array(
				'tl_class'  => 'clr',
				'doNotShow' => true,
			),
		),
		'offset'               => array(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array(
				'rgxp'     => 'natural',
				'tl_class' => 'w50',
			),
			'sql'       => "smallint(5) unsigned NOT NULL default 0",
		),
		'limit'                => array(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array(
				'rgxp'     => 'natural',
				'tl_class' => 'w50',
			),
			'sql'       => "smallint(5) unsigned NOT NULL default 0",
		),
		'enableCron'           => array(
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array(
				'submitOnChange' => true,
				'tl_class'       => 'clr',
			),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'cronLevel'            => array(
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => array(
				'minutely',
				'daily',
				'hourly',
				'weekly',
				'monthly',
				'yearly',
			),
			'eval'      => array(
				'tl_class' => 'w50',
			),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
	),
);
