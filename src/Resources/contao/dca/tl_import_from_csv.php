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

use Markocupic\ImportFromCsvBundle\Dca\TlImportFromCsv;

$GLOBALS['TL_DCA']['tl_import_from_csv'] = array(
	'config'      => array(
		'dataContainer' => 'Table',
		'sql'           => array(
			'keys' => array(
				'id' => 'primary',
			),
		),
		// Trigger onload- and onsubmit callbacks using annotations
	),
	'edit'        => array(
		'buttons_callback' => array(
			array(TlImportFromCsv::class, 'buttonsCallback'),
		),
	),
	'list'        => array(
		'sorting'           => array(
			'fields' => array('tstamp DESC'),
		),
		'label'             => array(
			'fields' => array('import_table'),
			'format' => '%s',
		),
		'global_operations' => array(),
		'operations'        => array(
			'edit'   => array(
				'label' => &$GLOBALS['TL_LANG']['MSC']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.gif',
			),
			'delete' => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
			),
			'show'   => array(
				'label' => &$GLOBALS['TL_LANG']['MSC']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.gif',
			),
		),
	),
	'palettes'    => array(
		'default'      => '{manual},explanation;{settings},import_table,selected_fields,field_separator,field_enclosure,import_mode,fileSRC,skipValidationFields,listLines;{limitAndOffset_settings},offset,limit;{cron_settings},enableCron',
		'report'       => 'report',
		'__selector__' => array('enableCron'),
	),
	'subpalettes' => array(
		'enableCron' => 'cronLevel',
	),
	'fields'      => array(
		'id'                   => array(
			'label'  => array('ID'),
			'search' => true,
			'sql'    => "int(10) unsigned NOT NULL auto_increment",
		),
		'tstamp'               => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'explanation'          => array(
			// Triger input_field callback using annotations
			'eval' => array('tl_class' => 'clr', 'doNotShow' => true),
		),
		'report'               => array(
			// Triger input_field callback using annotations
			'eval'  => array('tl_class' => 'clr', 'doNotShow' => true),
		),
		'import_table'         => array(
			'inputType' => 'select',
			// Triger options callback using annotations
			'eval'      => array('multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'field_separator'      => array(
			'inputType' => 'text',
			'default'   => ';',
			'eval'      => array('mandatory' => true, 'maxlength' => 1),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'field_enclosure'      => array(
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 1),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'import_mode'          => array(
			'inputType' => 'select',
			'options'   => array('append_entries', 'truncate_table'),
			'reference' => $GLOBALS['TL_LANG']['tl_import_from_csv'],
			'eval'      => array('multiple' => false, 'mandatory' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'selected_fields'      => array(
			'inputType' => 'checkbox',
			// Triger options callback using annotations
			'eval'      => array('multiple' => true, 'mandatory' => true),
			'sql'       => "blob NULL",
		),
		'skipValidationFields' => array(
			'inputType' => 'select',
			// Triger options callback using annotations
			'eval'      => array('multiple' => true, 'chosen' => true, 'mandatory' => false),
			'sql'       => "blob NULL",
		),
		'fileSRC'              => array(
			'inputType' => 'fileTree',
			'eval'      => array('multiple' => false, 'fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'mandatory' => true, 'extensions' => 'csv', 'submitOnChange' => true),
			'sql'       => "binary(16) NULL",
		),
		'listLines'            => array(
			// Triger input_field callback using annotations
			'eval' => array('tl_class' => 'clr', 'doNotShow' => true),
		),
		'offset'               => array(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
			'sql'       => "smallint(5) unsigned NOT NULL default 0",
		),
		'limit'                => array(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
			'sql'       => "smallint(5) unsigned NOT NULL default 0",
		),
		'enableCron'           => array(
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'cronLevel'            => array(
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => array('minutely', 'daily', 'hourly', 'weekly', 'monthly', 'yearly'),
			'eval'      => array('tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
	),
);
