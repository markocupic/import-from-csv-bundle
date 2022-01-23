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

namespace Markocupic\ImportFromCsvBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\File;
use Contao\FilesModel;
use Doctrine\DBAL\Connection;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ImportFromCsv
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var TwigEnvironment
     */
    private $twig;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * TlImportFromCsv constructor.
     */
    public function __construct(ContaoFramework $framework, Connection $connection, TranslatorInterface $translator, TwigEnvironment $twig, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->twig = $twig;
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

        $objFilesModel = $filesModelAdapter->findByUuid($dc->activeRecord->fileSRC);

        if (null === $objFilesModel || !is_file($this->projectDir.'/'.$objFilesModel->path)) {
            return (new Response(''))->getContent();
        }

        $objFile = new File($objFilesModel->path);

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
        $schemaManager = $this->connection->getSchemaManager();

        $arrTables = $schemaManager->listTableNames();

        return \is_array($arrTables) ? $arrTables : [];
    }

    /**
     * @Callback(table="tl_import_from_csv", target="fields.selectedFields.options")
     * @Callback(table="tl_import_from_csv", target="fields.skipValidationFields.options")
     */
    public function optionsCbGetTableColumns(DataContainer $dc): array
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $strTable = $dc->activeRecord->importTable;

        if (!$strTable) {
            return [];
        }

        $schemaManager = $this->connection->getSchemaManager();

        // Get a list of all lowercase column names
        $arrLCFields = $schemaManager->listTableColumns($strTable);

        if (!\is_array($arrLCFields)) {
            return [];
        }

        $controllerAdapter->loadDataContainer($strTable);
        $arrDcaFields = [];

        foreach (array_keys($GLOBALS['TL_DCA'][$strTable]['fields'] ?? []) as $k) {
            $arrDcaFields[strtolower($k)] = [
                'strField' => $k,
                'sql' => $GLOBALS['TL_DCA'][$strTable]['fields'][$k]['sql'] ?? null,
            ];
        }

        $arrOptions = [];

        foreach (array_keys($arrLCFields) as $k) {
            // If exists, take the column name from the DCA
            $strField = $arrDcaFields[$k] ? $arrDcaFields[$k]['strField'] : $k;
            $sql = $arrDcaFields[$k]['sql'];
            $strSql = isset($sql) ? sprintf(' <span class="ifcb-sql-descr">[%s]</span>', $sql) : '';
            $arrOptions[$strField] = $strField.$strSql;
        }

        return $arrOptions;
    }
}
