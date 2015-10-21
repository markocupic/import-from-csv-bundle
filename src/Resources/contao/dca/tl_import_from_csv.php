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
    'config'   => array(
        'dataContainer'   => 'Table',
        'sql'             => array(
            'keys' => array(
                'id' => 'primary',
            )
        ),
        'onload_callback' => array(
            array(
                'tl_import_from_csv',
                'setPalettes'
            )
        )
    ),
    // List
    'list'     => array(
        'sorting'           => array(
            'fields' => array('tstamp DESC'),
        ),
        'label'             => array(
            'fields' => array('import_table'),
            'format' => '%s'
        ),
        'global_operations' => array(),
        'operations'        => array(
            'edit'   => array(
                'label' => &$GLOBALS['TL_LANG']['MSC']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif'
            ),
            'delete' => array(
                'label'      => &$GLOBALS['TL_LANG']['MSC']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            ),
            'show'   => array(
                'label' => &$GLOBALS['TL_LANG']['MSC']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif'
            )
        )
    ),
    // Palettes
    'palettes' => array(
        'default' => '{manual},explanation;{settings},import_table,selected_fields,field_separator,field_enclosure,import_mode,fileSRC,listLines',
    ),
    // Fields
    'fields'   => array(

        'id'              => array(
            'label'  => array('ID'),
            'search' => true,
            'sql'    => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'          => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'explanation'     => array(
            'input_field_callback' => array(
                'tl_import_from_csv',
                'generateExplanationMarkup'
            ),
            'eval'                 => array('tl_class' => 'clr', 'doNotShow' => true)

        ),
        'report'          => array(
            'label'                => &$GLOBALS['TL_LANG']['tl_import_from_csv']['report'],
            'input_field_callback' => array('tl_import_from_csv', 'generateReportMarkup'),
            'eval'                 => array('tl_class' => 'clr', 'doNotShow' => true)

        ),
        'import_table'    => array(
            'label'            => &$GLOBALS['TL_LANG']['tl_import_from_csv']['import_table'],
            'inputType'        => 'select',
            'options_callback' => array('tl_import_from_csv', 'optionsCbGetTables'),
            'eval'             => array('multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'submitOnChange' => true,),
            'sql'              => "varchar(255) NOT NULL default ''"
        ),
        'field_separator' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_import_from_csv']['field_separator'],
            'inputType' => 'text',
            'default'   => ';',
            'eval'      => array('mandatory' => true,),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'field_enclosure' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_import_from_csv']['field_enclosure'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => false,),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'import_mode'     => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_import_from_csv']['import_mode'],
            'inputType' => 'select',
            'options'   => array('append_entries', 'truncate_table'),
            'reference' => $GLOBALS['TL_LANG']['tl_import_from_csv'],
            'eval'      => array('multiple' => false, 'mandatory' => true,),
            'sql'       => "varchar(255) NOT NULL default ''"

        ),
        'selected_fields' => array(
            'label'            => &$GLOBALS['TL_LANG']['tl_import_from_csv']['selected_fields'],
            'inputType'        => 'checkbox',
            'options_callback' => array('tl_import_from_csv', 'optionsCbSelectedFields'),
            'eval'             => array('multiple' => true, 'mandatory' => true),
            'sql'              => "varchar(1024) NOT NULL default ''"

        ),
        'fileSRC'         => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_import_from_csv']['fileSRC'],
            'inputType' => 'fileTree',
            'eval'      => array('multiple' => false, 'fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'mandatory' => true, 'extensions' => 'csv', 'submitOnChange' => true,),
            'sql'       => "binary(16) NULL"
        ),
        'listLines'       => array(
            'input_field_callback' => array('tl_import_from_csv', 'generateFileContent'),
            'eval'                 => array('tl_class' => 'clr', 'doNotShow' => true)

        )
    )
);

/**
 * Class tl_import_from_csv
 * Provide miscellaneous methods that are used by the data configuration array.
 * Copyright : &copy; 2014 Marko Cupic Sponsor der Erweiterung: Fast-Doc UG, Berlin
 * @author Marko Cupic 2014, extension sponsered by Rainer-Maria Fritsch - Fast-Doc UG, Berlin
 * @package import_from_csv
 */
class tl_import_from_csv extends Backend
{

    public function __construct()
    {

        parent::__construct();

        if (isset($_POST['saveNcreate']) && $this->Input->post('FORM_SUBMIT') && $this->Input->post('SUBMIT_TYPE') != 'auto' && !$_SESSION['import_from_csv'])
        {
            unset($_POST['saveNcreate']);
            $this->initImport();
        }
    }


    /**
     * onload_callback setPalettes
     */
    public function setPalettes()
    {

        if ($_SESSION['import_from_csv'] && !$this->Input->post('FORM_SUBMIT'))
        {
            $GLOBALS['TL_DCA']['tl_import_from_csv']['palettes']['default'] = 'report;';
        }
    }


    /**
     * init import
     */
    private function initImport()
    {

        $strTable = $this->Input->post('import_table');
        $importMode = $this->Input->post('import_mode');
        $arrSelectedFields = $this->Input->post('selected_fields');
        $strFieldseparator = $this->Input->post('field_separator');
        $strFieldenclosure = $this->Input->post('field_enclosure');

        $objFile = FilesModel::findByUuid($this->Input->post('fileSRC'));
        // call the import class if file exists
        if (is_file(TL_ROOT . '/' . $objFile->path))
        {
            $objFile = new File($objFile->path);
            if (strtolower($objFile->extension) == 'csv')
            {
                $objImport = new MCupic\ImportFromCsv\ImportFromCsv;
                $objImport->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strFieldseparator, $strFieldenclosure, 'id', '||');
            }
        }
    }


    /**
     * field_callback generateExplanationMarkup
     * @return string
     */
    public function generateExplanationMarkup()
    {

        return '
<div class="manual">
    <label><h2>Erklärungen</h2></label>
    <figure class="image_container"><img src="../system/modules/import_from_csv/assets/manual.jpg" title="ms-excel" style="width:100%" alt="manual"></figure>
    <p class="tl_help">CSV erstellt mit Tabellenkalkulationsprogramm (MS-Excel o.ä.)</p>
<br>
    <figure class="image_container"><img src="../system/modules/import_from_csv/assets/manual2.jpg" title="text-editor" style="width:100%" alt="manual"></figure>
    <p class="tl_help">CSV erstellt mit einfachem Texteditor</p>
<br>
    <p class="tl_help">Legen Sie mit Excel oder einem Texteditor ihrer Wahl eine Kommaseparierte Textdatei an (csv). In die erste Zeile schreiben Sie die korrekten Feldnamen. Die einzelnen Felder sollten durch ein Trennzeichen, üblicherweise das Semikolon ";", abgegrenzt werden. Feldinhalt, der in der Datenbank als serialisiertes Array abgelegt wird (z.B. Gruppenzugehörigkeiten), muss durch zwei aufeinanderfolgende pipe-Zeichen abgegrenzt werden "||". Feldbegrenzer und Feldtrennzeichen können individuell festgelegt werden. Wichtig! Beginnen Sie jeden Datensatz mit einer neuen Zeile. Keine Zeilenumbrüche im Datensatz.<br>Laden Sie die erstellte csv-Datei auf den Server. Anschliessend starten Sie den Importvorgang mit einem Klick auf den grossen Button.</p>
    <p class="tl_help">Beim Importvorgang werden die Inhalte auf Gültigkeit überprüft.</p>
    <p class="tl_help">Achtung! Nutzen Sie das Modul nur, wenn Sie sich ihrer Sache sicher sind. Gelöschte Daten können nur wiederhergestellt werden, wenn Sie vorher ein Backup gemacht haben.</p>

    <p><br>Weitere Hilfe gibt es unter: <a href="https://github.com/markocupic/import_from_csv">https://github.com/markocupic/import_from_csv</a></p>
</div>
             ';
    }


    /**
     * field_callback generateExplanationMarkup
     * @return string
     */
    public function generateFileContent()
    {

        $objDb = $this->Database->prepare('SELECT fileSRC FROM tl_import_from_csv WHERE id=?')->execute(Input::get('id'));
        $objFile = FilesModel::findByUuid($objDb->fileSRC);
        // call the import class if file exists
        if (!is_file(TL_ROOT . '/' . $objFile->path))
        {
            return;
        }

        $objFile = new File($objFile->path, true);
        $arrFileContent = $objFile->getContentAsArray();
        $fileContent = '';
        foreach ($arrFileContent as $line)
        {
            $fileContent .= '<p class="tl_help">' . $line . '</p>';
        }

        return '
<div class="parsedFile">
       <label><h2>' . $GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'][0] . '</h2></label>
       <div class="fileContentBox">
              <div>
                     ' . $fileContent . '
              </div>
       </div>
</div>
             ';
    }


    /**
     * field_callback generateReportMarkup
     * @return string
     */
    public function generateReportMarkup()
    {

        $html = '<h2>Systemmeldung:</h2>';
        $rows = $_SESSION['import_from_csv']['status']['rows'];
        $success = $_SESSION['import_from_csv']['status']['success'];
        $errors = $_SESSION['import_from_csv']['status']['errors'];

        $html .= sprintf('<p id="summary"><span>%s: %s</span><br><span class="allOk">%s: %s</span><br><span class="error">%s: %s</span></p>', $GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'], $rows, $GLOBALS['TL_LANG']['tl_import_from_csv']['successful_inserts'], $success, $GLOBALS['TL_LANG']['tl_import_from_csv']['failed_inserts'], $errors);

        $html .= '<table id="reportTable" class="reportTable">';
        if (is_array($_SESSION['import_from_csv']['report']))
        {
            foreach ($_SESSION['import_from_csv']['report'] as $row)
            {
                $html .= $row;
            }
        }
        unset($_SESSION['import_from_csv']);

        $html .= '</table>';
        return $html;

    }


    /**
     * option_callback
     * @return array
     */
    public function optionsCbGetTables()
    {

        $objTables = $this->Database->listTables();
        $arrOptions = array();
        foreach ($objTables as $table)
        {
            $arrOptions[] = $table;
        }
        return $arrOptions;
    }


    /**
     * option_callback
     * @return array
     */
    public function optionsCbSelectedFields()
    {

        $objDb = $this->Database->prepare('SELECT * FROM tl_import_from_csv WHERE id = ?')->execute($this->Input->get('id'));
        if ($objDb->import_table == '')
        {
            return;
        }
        $objFields = $this->Database->listFields($objDb->import_table, 1);
        $arrOptions = array();
        foreach ($objFields as $field)
        {
            if ($field['name'] == 'PRIMARY')
            {
                continue;
            }
            if (in_array($field['name'], $arrOptions))
            {
                continue;
            }
            $arrOptions[$field['name']] = $field['name'] . ' [' . $field['type'] . ']';
        }
        return $arrOptions;
    }


    /**
     * Parse Backend Template Hook
     * @param string
     * @param string
     * @return string
     */
    public function parseBackendTemplate($strContent, $strTemplate)
    {

        if (Input::get('act') == 'edit')
        {
            // Remove saveNClose button
            // Contao 3
            $strContent = preg_replace('/<input type=\"submit\" name=\"saveNclose\"((\r|\n|.)+?)>/', '', $strContent);
            // Contao 4
            $strContent = preg_replace('/<button type=\"submit\" name=\"saveNclose\"((\r|\n|.)+?)>((\r|\n|.)+?)button>/', '', $strContent);

            // Rename saveNcreate button
            // Contao 3
            $strContent = preg_replace('/<input type=\"submit\" name=\"saveNcreate\" id=\"saveNcreate\" class=\"tl_submit\" accesskey=\"n\" value=\"((\r|\n|.)+?)\">/', '<input type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit importButton" accesskey="n" value="' . $GLOBALS['TL_LANG']['tl_import_from_csv']['launchImportButton'] . '">', $strContent);
            // Contao 4
            $strContent = preg_replace('/<button type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)button>/', '<button type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit importButton" accesskey="n">' . $GLOBALS['TL_LANG']['tl_import_from_csv']['launchImportButton'] . '</button>', $strContent);

            // Remove buttons in reportTable view
            if (strstr($strContent, 'reportTable'))
            {
                // Contao 3
                $strContent = preg_replace('/<input type=\"submit\" name=\"save\"((\r|\n|.)+?)>/', '', $strContent);
                $strContent = preg_replace('/<input type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)>/', '', $strContent);

                // Contao 4
                $strContent = preg_replace('/<button type=\"submit\" name=\"save\"((\r|\n|.)+?)button>/', '', $strContent);
                $strContent = preg_replace('/<button type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)button>/', '', $strContent);
            }
        }

        return $strContent;
    }
}           
              