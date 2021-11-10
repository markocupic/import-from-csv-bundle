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

namespace Markocupic\ImportFromCsvBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Database;
use Contao\DataContainer;
use Contao\File;
use Contao\FilesModel;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvHelper;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TlImportFromCsv
{
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
     * @var ImportFromCsvHelper
     */
    private $importHelper;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * TlImportFromCsv constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, SessionInterface $session, TranslatorInterface $translator, TwigEnvironment $twig, ImportFromCsvHelper $importHelper, string $projectDir)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->importHelper = $importHelper;
        $this->projectDir = $projectDir;
    }

    /**
     * @Callback(table="tl_import_from_csv", target="fields.explanation.input_field")
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateExplanationMarkup(): string
    {
        return $this->twig->render(
            '@MarkocupicImportFromCsv/help_text.html.twig',
            [
                'help_text' => $this->translator->trans('tl_import_from_csv.infoText', [], 'contao_default'),
            ]
        );
    }

    /**
     * @Callback(table="tl_import_from_csv", target="fields.listLines.input_field")
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateFileContentMarkup(DataContainer $dc): string
    {
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);

        $objModel = $importFromCsvModelAdapter->findByPk($dc->activeRecord->id);
        $objFilesModel = $filesModelAdapter->findByUuid($objModel->fileSRC);

        if (null === $objFilesModel || !is_file($this->projectDir.'/'.$objFilesModel->path)) {
            return (new Response(''))->getContent();
        }

        $objFile = new File($objFilesModel->path, true);

        return $this->twig->render(
            '@MarkocupicImportFromCsv/file_content.html.twig',
            [
                'headline' => $this->translator->trans('tl_import_from_csv.fileContent.0', [], 'contao_default'),
                'rows' => $objFile->getContentAsArray(),
            ]
        );
    }

    /**
     * @Callback(table="tl_import_from_csv", target="fields.importTable.options")
     */
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

    /**
     * @Callback(table="tl_import_from_csv", target="fields.selectedFields.options")
     * @Callback(table="tl_import_from_csv", target="fields.skipValidationFields.options")
     */
    public function optionsCbSelectedFields(DataContainer $dc): array
    {
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $objModel = $importFromCsvModelAdapter->findByPk($dc->activeRecord->id);

        if (null === $objModel || '' === $objModel->importTable) {
            return [];
        }

        $controllerAdapter->loadDataContainer($objModel->importTable);

        $arrFields = $GLOBALS['TL_DCA'][$objModel->importTable]['fields'];

        if (!isset($arrFields) || !\is_array($arrFields)) {
            return [];
        }

        $arrOptions = [];

        foreach ($arrFields as $fieldname => $arrField) {
            if (!isset($fieldname)) {
                continue;
            }

            $sql = $arrField['sql'] ?? '';

            $arrOptions[$fieldname] = sprintf('%s <span class="ifcb-sql-descr">[%s]</span>', $fieldname, $sql);
        }

        return $arrOptions;
    }
}
