<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\TlDca;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DC_Table;
use Contao\File;
use Contao\FilesModel;
use Contao\Input;
use League\Csv\Exception;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class TlImportFromCsv.
 */
class TlImportFromCsv
{
    /**
     * @var bool
     */
    protected $reportTableMode = false;
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Request
     */
    private $requestStack;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ImportFromCsv
     */
    private $importer;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * TlImportFromCsv constructor.
     *
     * @throws Exception
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, SessionInterface $session, ImportFromCsv $importer, string $projectDir)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->importer = $importer;
        $this->projectDir = $projectDir;

        $request = $this->requestStack->getCurrentRequest();
        $bag = $this->session->getBag('contao_backend');

        if ($request->request->has('saveNcreate') || $request->request->has('saveNclose') && 'tl_import_from_csv' === $request->request('FORM_SUBMIT') && 'auto' !== $request->request('SUBMIT_TYPE') && !isset($bag['import_from_csv'])) {
            $blnTestMode = false;

            if ($request->request->has('saveNcreate')) {
                unset($_POST['saveNcreate']);
            }

            if ($request->request->has('saveNclose')) {
                $blnTestMode = true;
                unset($_POST['saveNclose']);
            }
            $this->initImport($blnTestMode);
        }
    }

    public function setPalettes(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $bag = $this->session->getBag('contao_backend');

        if (isset($bag['import_from_csv']) && !$request->request->has('FORM_SUBMIT')) {
            // Set  $this->reportTableMode to true. This is used in the buttonsCallback
            $this->reportTableMode = true;

            $GLOBALS['TL_DCA']['tl_import_from_csv']['palettes']['default'] = 'report;';
        }
    }

    /**
     * @return string
     */
    public function generateExplanationMarkup()
    {
        return <<<EOT
<div class="widget manual">
    <label><h2>Erklärungen</h2></label>
    <figure class="image_container"><img src="bundles/markocupicimportfromcsv/manual.jpg" title="ms-excel" style="width:100%" alt="manual"></figure>
    <p class="tl_help">CSV erstellt mit Tabellenkalkulationsprogramm (MS-Excel o.ä.)</p>
<br>
    <figure class="image_container"><img src="bundles/markocupicimportfromcsv/manual2.jpg" title="text-editor" style="width:100%" alt="manual"></figure>
    <p class="tl_help">CSV erstellt mit einfachem Texteditor</p>
<br>
    <p class="tl_help">Mit MS-Excel oder einem Texteditor lässt sich eine kommaseparierte Textdatei anlegen (csv). In die erste Zeile gehören die Feldnamen. Die einzelnen Felder sollten durch ein Trennzeichen (üblicherweise das Semikolon ";") abgegrenzt werden. Feldinhalt, der in der Datenbank als serialisiertes Array abgelegt wird (z.B. Gruppenzugehörigkeiten), muss durch zwei aufeinanderfolgende pipe-Zeichen abgegrenzt werden z.B. "2||5". Feldbegrenzer und Feldtrennzeichen können individuell festgelegt werden. Wichtig! Jeder Datensatz gehört auf eine neue Zeile. Zeilenumbrüche im Datensatz verunmöglichen den Import.<br>Die erstellte csv-Datei muss über die Daeiverwaltung auf den Webserver geladen werden. Anschliessend kann der Importvorgang unter dem Splitbutton gestartet werden.</p>
    <p class="tl_help">Beim Importvorgang werden die Inhalte auf Gültigkeit überprüft.</p>
    <p class="tl_help">Achtung! Das Modul sollte nur genutzt werden, wenn man sich seiner Sache sehr sicher ist. Gelöschte Daten können nur wiederhergestellt werden, wenn vorher ein Datenbankbackup erstellt worden ist.</p>

    <p><br>Weitere Hilfe gibt es unter: <a href="https://github.com/markocupic/import-from-csv-bundle">https://github.com/markocupic/import-from-csv-bundle</a></p>
</div>
EOT;
    }

    /**
     * @throws \Exception
     */
    public function generateFileContentMarkup(): string
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $objDb = $databaseAdapter
            ->getInstance()
            ->prepare('SELECT fileSRC FROM tl_import_from_csv WHERE id=?')
            ->execute($inputAdapter->get('id'))
        ;
        $objFile = $filesModelAdapter->findByUuid($objDb->fileSRC);

        // Only launch the import script if file exists
        if (!is_file($this->projectDir.'/'.$objFile->path)) {
            return '';
        }

        $objFile = new File($objFile->path, true);
        $arrFileContent = $objFile->getContentAsArray();
        $fileContent = '';

        foreach ($arrFileContent as $line) {
            $fileContent .= '<p class="tl_help">'.$line.'</p>';
        }

        $fc = $GLOBALS['TL_LANG']['tl_import_from_csv']['fileContent'][0];

        return <<<EOT
<div class="widget parsedFile">
  <p></p>
  <label>
    <h2>$fc</h2>
  </label>
  <div class="fileContentBox">
    <div>$fileContent</div>
  </div>
</div>
EOT;
    }

    public function generateReportMarkup(): string
    {
        $bag = $this->session->getBag('contao_backend');

        // Html
        $html = '<div class="widget"><h2>Importübersicht:</h2>';
        $rows = $bag['import_from_csv']['status']['rows'];
        $success = $bag['import_from_csv']['status']['success'];
        $errors = $bag['import_from_csv']['status']['errors'];
        $offset = $bag['import_from_csv']['status']['offset'];
        $limit = $bag['import_from_csv']['status']['limit'];

        if ($bag['import_from_csv']['status']['blnTestMode'] > 0) {
            $html .= '<h3>Testmode: ON</h3><br>';
        }

        $html .= sprintf('<p id="summary"><span>%s: %s</span><br><span>Offset: %s</span><br><span>Limit: %s</span><br><span class="allOk">%s: %s</span><br><span class="error">%s: %s</span></p>', $GLOBALS['TL_LANG']['tl_import_from_csv']['datarecords'], $rows, $offset, $limit, $GLOBALS['TL_LANG']['tl_import_from_csv']['successful_inserts'], $success, $GLOBALS['TL_LANG']['tl_import_from_csv']['failed_inserts'], $errors);

        $html .= '<table id="reportTable" class="reportTable">';

        if (\is_array($bag['import_from_csv']['report'])) {
            foreach ($bag['import_from_csv']['report'] as $row) {
                $html .= $row;
            }
        }

        unset($bag['import_from_csv']);
        $this->session->set('contao_backend', $bag);

        return $html.'</table></div>';
    }

    public function optionsCbGetTables(): array
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        $objTables = $databaseAdapter
            ->getInstance()
            ->listTables()
        ;

        $arrOptions = [];

        foreach ($objTables as $table) {
            $arrOptions[] = $table;
        }

        return $arrOptions;
    }

    /**
     * @return array
     */
    public function optionsCbSelectedFields()
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $objDb = $databaseAdapter
            ->getInstance()
            ->prepare('SELECT * FROM tl_import_from_csv WHERE id = ?')
            ->execute($inputAdapter->get('id'))
        ;

        $arrOptions = [];

        if (empty($objDb->import_table)) {
            return $arrOptions;
        }
        $objFields = $databaseAdapter
            ->getInstance()
            ->listFields($objDb->import_table, 1)
        ;

        foreach ($objFields as $field) {
            if ('PRIMARY' === $field['name']) {
                continue;
            }

            if (\in_array($field['name'], $arrOptions, true)) {
                continue;
            }

            $arrOptions[$field['name']] = $field['name'].' ['.$field['type'].']';
        }

        return $arrOptions;
    }

    public function buttonsCallback(array $arrButtons, DC_Table $dc): array
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        if ('edit' === $inputAdapter->get('act')) {
            $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit testButton" accesskey="n">'.$GLOBALS['TL_LANG']['tl_import_from_csv']['testRunImportButton'].'</button>';
            $arrButtons['saveNcreate'] = '<button type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit importButton" accesskey="n">'.$GLOBALS['TL_LANG']['tl_import_from_csv']['launchImportButton'].'</button>';
            unset($arrButtons['saveNduplicate']);
        }

        // Remove buttons in reportTable view
        if (true === $this->reportTableMode) {
            unset($arrButtons['save'], $arrButtons['saveNclose'], $arrButtons['saveNcreate'], $arrButtons['saveNduplicate']);
        }

        return $arrButtons;
    }

    /**
     * @throws Exception
     */
    private function initImport(bool $blnTestMode): void
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $strTable = $inputAdapter->post('import_table');
        $importMode = $inputAdapter->post('import_mode');
        $arrSelectedFields = !empty($inputAdapter->post('selected_fields')) && \is_array($inputAdapter->post('selected_fields')) ? $inputAdapter->post('selected_fields') : [];
        $strDelimiter = $inputAdapter->post('field_separator');
        $strEnclosure = $inputAdapter->post('field_enclosure');
        $intOffset = (int) $inputAdapter->post('offset', 0);
        $intLimit = (int) $inputAdapter->post('limit', 0);
        $arrSkipValidationFields = !empty($inputAdapter->post('skipValidationFields')) && \is_array($inputAdapter->post('skipValidationFields')) ? $inputAdapter->post('skipValidationFields') : [];
        $objFile = $filesModelAdapter->findByUuid($inputAdapter->post('fileSRC'));

        // call the import class if file exists
        if (is_file($this->projectDir.'/'.$objFile->path)) {
            $objFile = new File($objFile->path);

            if ('csv' === strtolower($objFile->extension)) {
                $this->importer->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strDelimiter, $strEnclosure, '||', $blnTestMode, $arrSkipValidationFields, $intOffset, $intLimit);
            }
        }
    }
}
