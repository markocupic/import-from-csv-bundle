<?php

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 19.02.2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

use Contao\File;
use Contao\FilesModel;
use Contao\Input;
use League\Csv\Exception;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;

/**
 * Class tl_import_from_csv
 */
class tl_import_from_csv extends Backend
{
	/**
	 * @var bool
	 */
	protected $reportTableMode = false;

	/**
	 * tl_import_from_csv constructor.
	 */
	public function __construct()
	{
		parent::__construct();

        $session = \Contao\System::getContainer()->get('session');
        $bag = $session->getBag('contao_backend');

		if ((isset($_POST['saveNcreate']) || isset($_POST['saveNclose'])) && Input::post('FORM_SUBMIT') == 'tl_import_from_csv' && Input::post('SUBMIT_TYPE') != 'auto' && !isset($bag['import_from_csv']))
		{
			$blnTestMode = false;

			if (isset($_POST['saveNcreate']))
			{
				unset($_POST['saveNcreate']);
			}

			if (isset($_POST['saveNclose']))
			{
				$blnTestMode = true;
				unset($_POST['saveNclose']);
			}
			$this->initImport($blnTestMode);
		}
	}

	/**
	 * @param $blnTestMode
	 * @throws Exception
	 */
	private function initImport(bool $blnTestMode): void
	{
		$strTable = Input::post('import_table');
		$importMode = Input::post('import_mode');
		$arrSelectedFields = (!empty(Input::post('selected_fields')) && is_array(Input::post('selected_fields'))) ? Input::post('selected_fields') : array();
		$strDelimiter = Input::post('field_separator');
		$strEnclosure = Input::post('field_enclosure');
		$intOffset = (int) Input::post('offset', 0);
		$intLimit = (int) Input::post('limit', 0);
		$arrSkipValidationFields = (!empty(Input::post('skipValidationFields')) && is_array(Input::post('skipValidationFields'))) ? Input::post('skipValidationFields') : array();
		$objFile = FilesModel::findByUuid(Input::post('fileSRC'));

		// call the import class if file exists
		if (is_file(TL_ROOT . '/' . $objFile->path))
		{
			$objFile = new File($objFile->path);

			if (strtolower($objFile->extension) === 'csv')
			{
				$objImport = \Contao\System::getContainer()->get(Markocupic\ImportFromCsvBundle\Import\ImportFromCsv::class);
				$objImport->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strDelimiter, $strEnclosure, '||', $blnTestMode, $arrSkipValidationFields, $intOffset, $intLimit);
			}
		}
	}

	/**
	 * onload_callback setPalettes
	 */
	public function setPalettes()
	{
        $session = \Contao\System::getContainer()->get('session');
        $bag = $session->getBag('contao_backend');

	    if (isset($bag['import_from_csv']) && !Input::post('FORM_SUBMIT'))
		{
			// Set  $this->reportTableMode to true. This is used in the buttonsCallback
			$this->reportTableMode = true;

			$GLOBALS['TL_DCA']['tl_import_from_csv']['palettes']['default'] = 'report;';
		}
	}

	/**
	 * field_callback generateExplanationMarkup
	 * @return string
	 */
	public function generateExplanationMarkup()
	{
		return '
<div class="widget manual">
    <label><h2>Erkl&auml;rungen</h2></label>
    <figure class="image_container"><img src="bundles/markocupicimportfromcsv/manual.jpg" title="ms-excel" style="width:100%" alt="manual"></figure>
    <p class="tl_help">CSV erstellt mit Tabellenkalkulationsprogramm (MS-Excel o.&auml;.)</p>
<br>
    <figure class="image_container"><img src="bundles/markocupicimportfromcsv/manual2.jpg" title="text-editor" style="width:100%" alt="manual"></figure>
    <p class="tl_help">CSV erstellt mit einfachem Texteditor</p>
<br>
    <p class="tl_help">Mit MS-Excel oder einem Texteditor l&auml;sst sich eine kommaseparierte Textdatei anlegen (csv). In die erste Zeile geh&ouml;ren die Feldnamen. Die einzelnen Felder sollten durch ein Trennzeichen (&uuml;blicherweise das Semikolon ";") abgegrenzt werden. Feldinhalt, der in der Datenbank als serialisiertes Array abgelegt wird (z.B. Gruppenzugeh&ouml;rigkeiten), muss durch zwei aufeinanderfolgende pipe-Zeichen abgegrenzt werden z.B. "2||5". Feldbegrenzer und Feldtrennzeichen k&ouml;nnen individuell festgelegt werden. Wichtig! Jeder Datensatz geh&ouml;rt auf eine neue Zeile. Zeilenumbr&uuml;che im Datensatz verunm&ouml;glichen den Import.<br>Die erstellte csv-Datei muss &uuml;ber die Daeiverwaltung auf den Webserver geladen werden. Anschliessend kann der Importvorgang unter dem Splitbutton gestartet werden.</p>
    <p class="tl_help">Beim Importvorgang werden die Inhalte auf G&uuml;ltigkeit &uuml;berpr&uuml;ft.</p>
    <p class="tl_help">Achtung! Das Modul sollte nur genutzt werden, wenn man sich seiner Sache sehr sicher ist. Gel&ouml;schte Daten k&ouml;nnen nur wiederhergestellt werden, wenn vorher ein Datenbankbackup erstellt worden ist.</p>

    <p><br>Weitere Hilfe gibt es unter: <a href="https://github.com/markocupic/import-from-csv-bundle">https://github.com/markocupic/import-from-csv-bundle</a></p>
</div>
             ';
	}

	/**
	 * field_callback generateExplanationMarkup
	 * @return string
	 */
	public function generateFileContentMarkup()
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
<div class="widget parsedFile">
       <br>
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
        $session = \Contao\System::getContainer()->get('session');
        $bag = $session->getBag('contao_backend');

	    // Html
		$html = '<div class="widget"><h2>Importübersicht:</h2>';
		$rows = $bag['import_from_csv']['status']['rows'];
		$success =$bag['import_from_csv']['status']['success'];
		$errors =$bag['import_from_csv']['status']['errors'];
		$offset = $bag['import_from_csv']['status']['offset'];
		$limit = $bag['import_from_csv']['status']['limit'];

		if ($bag['import_from_csv']['status']['blnTestMode'] > 0)
		{
			$html .= '<h3>Testmode: ON</h3><br>';
		}

		$html .= sprintf('<p id="summary"><span>%s: %s</span><br><span>Offset: %s</span><br><span>Limit: %s</span><br><span class="allOk">%s: %s</span><br><span class="error">%s: %s</span></p>', $GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'], $rows, $offset, $limit, $GLOBALS['TL_LANG']['tl_import_from_csv']['successful_inserts'], $success, $GLOBALS['TL_LANG']['tl_import_from_csv']['failed_inserts'], $errors);

		$html .= '<table id="reportTable" class="reportTable">';

		if (is_array($bag['import_from_csv']['report']))
		{
			foreach ($bag['import_from_csv']['report'] as $row)
			{
				$html .= $row;
			}
		}

		unset($bag['import_from_csv']);
		$session->set('contao_backend', $bag);

		$html .= '</table></div>';

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
		$objDb = $this->Database->prepare('SELECT * FROM tl_import_from_csv WHERE id = ?')->execute(Input::get('id'));

		if (empty($objDb->import_table))
		{
			return;
		}
		$objFields = $this->Database->listFields($objDb->import_table, 1);
		$arrOptions = array();

		foreach ($objFields as $field)
		{
			if ($field['name'] === 'PRIMARY')
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
	 * @param $arrButtons
	 * @param  DC_Table $dc
	 * @return mixed
	 */
	public function buttonsCallback($arrButtons, DC_Table $dc)
	{
		if (Input::get('act') === 'edit')
		{
			$arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit testButton" accesskey="n">' . $GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportButton'] . '</button>';
			$arrButtons['saveNcreate'] = '<button type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit importButton" accesskey="n">' . $GLOBALS['TL_LANG']['tl_import_from_csv']['launchImportButton'] . '</button>';
			unset($arrButtons['saveNduplicate']);
		}

		// Remove buttons in reportTable view
		if ($this->reportTableMode === true)
		{
			unset($arrButtons['save'], $arrButtons['saveNclose'], $arrButtons['saveNcreate'], $arrButtons['saveNduplicate']);
		}

		return $arrButtons;
	}
}
