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

namespace Markocupic\ImportFromCsvBundle\Dca;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\File;
use Contao\FilesModel;
use Contao\Input;
use League\Csv\Exception;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class TlImportFromCsv.
 */
class TlImportFromCsv
{
    /**
     * @var bool
     */
    private $reportTableMode = false;

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
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var TwigEnvironment
     */
    private $twig;

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
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, SessionInterface $session, TranslatorInterface $translator, TwigEnvironment $twig, ImportFromCsv $importer, string $projectDir)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->importer = $importer;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws Exception
     */
    public function route(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $bag = $this->session->getBag('contao_backend');

        if ('tl_import_from_csv' === $request->request->get('FORM_SUBMIT') && 'auto' !== $request->request->get('SUBMIT_TYPE')) {
            if (!isset($bag[ImportFromCsv::SESSION_BAG_KEY])) {
                if ($request->request->has('import') || $request->request->has('importTest')) {
                    $blnTestMode = false;

                    if ($request->request->has('importTest')) {
                        $blnTestMode = true;
                    }
                    // Set $_POST['save'] thus the input will be saved
                    $request->request->set('save', true);

                    // Lauch import script
                    $this->initImport($blnTestMode);
                }
            }
        }

        // Set report mode
        if (isset($bag[ImportFromCsv::SESSION_BAG_KEY]) && !$request->request->has('FORM_SUBMIT')) {
            $this->reportTableMode = true;
        }
    }

    public function setPalettes(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $bag = $this->session->getBag('contao_backend');

        if (isset($bag[ImportFromCsv::SESSION_BAG_KEY]) && !$request->request->has('FORM_SUBMIT')) {
            $GLOBALS['TL_DCA']['tl_import_from_csv']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_import_from_csv']['palettes']['report'];
        }
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateExplanationMarkup(): string
    {
        return $this->twig->render(
            '@MarkocupicImportFromCsv/ImportFromCsv/help_text.html.twig',
            [
                'help_text' => $this->translator->trans('tl_import_from_csv.infoText', [], 'contao_default'),
            ]
        );
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
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

        $objFilesModel = $filesModelAdapter->findByUuid($objDb->fileSRC);

        // Only launch the import script if file exists
        if (null === $objFilesModel || !is_file($this->projectDir.'/'.$objFilesModel->path)) {
            return '';
        }

        $objFile = new File($objFilesModel->path, true);

        return $this->twig->render(
            '@MarkocupicImportFromCsv/ImportFromCsv/file_content.html.twig',
            [
                'headline' => $this->translator->trans('tl_import_from_csv.fileContent.0', [], 'contao_default'),
                'rows' => $objFile->getContentAsArray(),
            ]
        );
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateReportMarkup(): string
    {
        $bag = $this->session->getBag('contao_backend');

        $arrHead = [
            // Title
            'lang_title' => $this->translator->trans('tl_import_from_csv.importOverview', [], 'contao_default'),
            // Labels
            'lang_datarecords' => $this->translator->trans('tl_import_from_csv.datarecords', [], 'contao_default'),
            'lang_successfull_inserts' => $this->translator->trans('tl_import_from_csv.successfullInserts', [], 'contao_default'),
            'lang_failed_inserts' => $this->translator->trans('tl_import_from_csv.failedInserts', [], 'contao_default'),
            'lang_show_errors_btn' => $this->translator->trans('tl_import_from_csv.showErrorsButton', [], 'contao_default'),
            'lang_show_all_btn' => $this->translator->trans('tl_import_from_csv.showAllButton', [], 'contao_default'),
            // Values
            'count_rows' => $bag[ImportFromCsv::SESSION_BAG_KEY]['status']['rows'],
            'count_success' => $bag[ImportFromCsv::SESSION_BAG_KEY]['status']['success'],
            'count_errors' => $bag[ImportFromCsv::SESSION_BAG_KEY]['status']['errors'],
            'int_offset' => $bag[ImportFromCsv::SESSION_BAG_KEY]['status']['offset'],
            'int_limit' => $bag[ImportFromCsv::SESSION_BAG_KEY]['status']['limit'],
            'is_testmode' => $bag[ImportFromCsv::SESSION_BAG_KEY]['status']['blnTestMode'] > 0 ? true : false,
        ];

        $arrReport = $bag[ImportFromCsv::SESSION_BAG_KEY]['report'];
        $arrRows = \is_array($arrReport) ? $arrReport : [];

        unset($bag[ImportFromCsv::SESSION_BAG_KEY]);
        $this->session->set('contao_backend', $bag);

        return $this->twig->render(
            '@MarkocupicImportFromCsv/ImportFromCsv/import_report.html.twig',
            [
                'head' => $arrHead,
                'rows' => $arrRows,
            ]
        );
    }

    public function optionsCbGetTables(): array
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        $arrTables = $databaseAdapter
            ->getInstance()
            ->listTables()
        ;

        return \is_array($arrTables) ? $arrTables : [];
    }

    public function optionsCbSelectedFields(): array
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $objDb = $databaseAdapter
            ->getInstance()
            ->prepare('SELECT * FROM tl_import_from_csv WHERE id = ?')
            ->execute($inputAdapter->get('id'))
        ;

        if ('' === $objDb->import_table) {
            return [];
        }

        $controllerAdapter->loadDataContainer($objDb->import_table);

        $arrFields = $GLOBALS['TL_DCA'][$objDb->import_table]['fields'];

        if (!isset($arrFields) || !\is_array($arrFields)) {
            return [];
        }

        $arrOptions = [];

        foreach ($arrFields as $fieldname => $arrField) {
            if (!isset($fieldname)) {
                continue;
            }

            $sql = $arrField['sql'] ?? '';

            $arrOptions[$fieldname] = sprintf('%s [%s]', $fieldname, $sql);
        }

        return $arrOptions;
    }

    public function buttonsCallback(array $arrButtons, DC_Table $dc): array
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        if ('edit' === $inputAdapter->get('act')) {
            // Add import buttons
            $arrButtons['importTest'] = '<button type="submit" name="importTest" id="importTestBtn" class="tl_submit import-test-button" accesskey="t">'.$this->translator->trans('tl_import_from_csv.testRunImportButton', [], 'contao_default').'</button>';
            $arrButtons['import'] = '<button type="submit" name="import" id="importBtn" class="tl_submit import-button" accesskey="i">'.$this->translator->trans('tl_import_from_csv.runImportButton', [], 'contao_default').'</button>';

            // Hide buttons
            unset($arrButtons['saveNduplicate'], $arrButtons['saveNcreate'], $arrButtons['saveNclose']);
        }

        // Remove buttons in reportTable view
        if (true === $this->reportTableMode) {
            $arrButtons = [];
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
