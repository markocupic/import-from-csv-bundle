<?php
/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 * @package import_from_csv
 * @author Marko Cupic 2014, extension sponsered by Rainer-Maria Fritsch - Fast-Doc UG, Berlin
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 * @link https://github.com/markocupic/import_from_csv
 */

$GLOBALS['TL_DCA']['tl_import_from_csv'] = array(
    // Config
    'config' => array(
        'dataContainer' => 'Table',
        'sql' => array(
            'keys' => array(
                'id' => 'primary',
            ),
        ),
        'onload_callback' => array(
            array(
                'tl_import_from_csv',
                'setPalettes',
            ),
        ),
    ),
    'edit' => array(
        'buttons_callback' => array(
            array('tl_import_from_csv', 'buttonsCallback')
        )
    ),
    // List
    'list' => array(
        'sorting' => array(
            'fields' => array('tstamp DESC'),
        ),
        'label' => array(
            'fields' => array('import_table'),
            'format' => '%s',
        ),
        'global_operations' => array(),
        'operations' => array(
            'edit' => array(
                'label' => &$GLOBALS['TL_LANG']['MSC']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ),
            'delete' => array(
                'label' => &$GLOBALS['TL_LANG']['MSC']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
            'show' => array(
                'label' => &$GLOBALS['TL_LANG']['MSC']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ),
        ),
    ),
    // Palettes
    'palettes' => array(
        'default' => '{manual},explanation;{settings},import_table,selected_fields,field_separator,field_enclosure,import_mode,fileSRC,listLines',
    ),
    // Fields
    'fields' => array(

        'id' => array(
            'label' => array('ID'),
            'search' => true,
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp' => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'explanation' => array(
            'input_field_callback' => array(
                'tl_import_from_csv',
                'generateExplanationMarkup',
            ),
            'eval' => array('tl_class' => 'clr', 'doNotShow' => true),

        ),
        'report' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['report'],
            'input_field_callback' => array('tl_import_from_csv', 'generateReportMarkup'),
            'eval' => array('tl_class' => 'clr', 'doNotShow' => true),

        ),
        'import_table' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table'],
            'inputType' => 'select',
            'options_callback' => array('tl_import_from_csv', 'optionsCbGetTables'),
            'eval' => array('multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true,),
            'sql' => "varchar(255) NOT NULL default ''",
        ),
        'field_separator' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator'],
            'inputType' => 'text',
            'default' => ';',
            'eval' => array('mandatory' => true,),
            'sql' => "varchar(255) NOT NULL default ''",
        ),
        'field_enclosure' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure'],
            'inputType' => 'text',
            'eval' => array('mandatory' => false,),
            'sql' => "varchar(255) NOT NULL default ''",
        ),
        'import_mode' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode'],
            'inputType' => 'select',
            'options' => array('append_entries', 'truncate_table'),
            'reference' => $GLOBALS['TL_LANG']['tl_import_from_csv'],
            'eval' => array('multiple' => false, 'mandatory' => true,),
            'sql' => "varchar(255) NOT NULL default ''",

        ),
        'selected_fields' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['selected_fields'],
            'inputType' => 'checkbox',
            'options_callback' => array('tl_import_from_csv', 'optionsCbSelectedFields'),
            'eval' => array('multiple' => true, 'mandatory' => true),
            'sql' => "varchar(1024) NOT NULL default ''",

        ),
        'fileSRC' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'],
            'inputType' => 'fileTree',
            'eval' => array('multiple' => false, 'fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'mandatory' => true, 'extensions' => 'csv', 'submitOnChange' => true,),
            'sql' => "binary(16) NULL",
        ),
        'listLines' => array(
            'input_field_callback' => array('tl_import_from_csv', 'generateFileContent'),
            'eval' => array('tl_class' => 'clr', 'doNotShow' => true),

        ),
    ),
);



              