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
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ImportFromCsv
{
    private ContaoFramework $framework;
    private Connection $connection;
    private TranslatorInterface $translator;
    private TwigEnvironment $twig;
    private string $projectDir;

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
                'help_text' => $this->translator->trans('tl_import_from_csv.info_text', [], 'contao_default'),
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
     *
     * @throws Exception
     */
    public function optionsCbGetTables(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        $arrTables = $schemaManager->listTableNames();

        return \is_array($arrTables) ? $arrTables : [];
    }

    /**
     * @Callback(table="tl_import_from_csv", target="fields.selectedFields.options")
     * @Callback(table="tl_import_from_csv", target="fields.skipValidationFields.options")
     *
     * @throws Exception
     */
    public function optionsCbGetTableColumns(DataContainer $dc): array
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $tableName = $dc->activeRecord->importTable;

        if (!$tableName) {
            return [];
        }

        $schemaManager = $this->connection->createSchemaManager();

        // Get a list of all lowercase column names
        $arrLCFields = $schemaManager->listTableColumns($tableName);

        if (!\is_array($arrLCFields)) {
            return [];
        }

        $controllerAdapter->loadDataContainer($tableName);
        $arrDcaFields = [];

        foreach (array_keys($GLOBALS['TL_DCA'][$tableName]['fields'] ?? []) as $k) {
            $arrDcaFields[strtolower($k)] = [
                'strField' => $k,
                'sql' => $GLOBALS['TL_DCA'][$tableName]['fields'][$k]['sql'] ?? null,
            ];
        }

        $arrOptions = [];

        foreach (array_keys($arrLCFields) as $k) {
            // If exists, take the column name from the DCA
            $strField = $arrDcaFields[$k]['strField'] ?? $k;
            $sql = $arrDcaFields[$k]['sql'] ?? '';
            $strSql = !empty($sql) ? sprintf(' <span class="ifcb-sql-descr">[%s]</span>', $sql) : '';
            $arrOptions[$strField] = $strField.$strSql;
        }

        return $arrOptions;
    }
}
