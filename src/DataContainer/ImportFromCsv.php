<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\DataContainer;
use Contao\FilesModel;
use Doctrine\DBAL\Connection;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Reader\CsvLineReader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

readonly class ImportFromCsv
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private CsvLineReader $csvLineReader,
        private TranslatorInterface $translator,
        private TwigEnvironment $twig,
        #[Autowire('%markocupic_import_from_csv.preview_limit%')]
        private int $previewLimit,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    #[AsCallback(table: 'tl_import_from_csv', target: 'fields.explanation.input_field', priority: 100)]
    public function generateExplanationMarkup(): string
    {
        return $this->twig->render('@MarkocupicImportFromCsv/help_text.html.twig', [
            'help_text' => $this->translator->trans('tl_import_from_csv.info_text', [], 'contao_default'),
        ]);
    }

    #[AsCallback(table: 'tl_import_from_csv', target: 'fields.listLines.input_field', priority: 100)]
    public function generateFileContentMarkup(DataContainer $dc): string
    {
        $filesModel = $this->framework
            ->getAdapter(FilesModel::class)
            ->findByUuid($dc->activeRecord->fileSRC)
        ;

        if (null === $filesModel) {
            return '';
        }

        $filePath = Path::join($this->projectDir, $filesModel->path);
        $offset = 0;

        try {
            $splFile = new \SplFileObject($filePath);
            $rows = $this->csvLineReader->readLines(file: $splFile, offset: $offset, limit: $this->previewLimit);
            $truncated = $this->csvLineReader->countLines(file: $splFile) > $this->previewLimit;
        } catch (\Throwable $e) {
            return $this->twig->render('@MarkocupicImportFromCsv/file_content.html.twig', [
                'headline' => $this->translator->trans('tl_import_from_csv.fileContent.0', [], 'contao_default'),
                'has_error' => true,
                'exception' => $e,
                'error_message' => $this->translator->trans('tl_import_from_csv.could_not_load_file', [$filesModel->path], 'contao_default'),
            ]);
        }

        return $this->twig->render('@MarkocupicImportFromCsv/file_content.html.twig', [
            'headline' => $this->translator->trans('tl_import_from_csv.fileContent.0', [], 'contao_default'),
            'rows' => $rows,
            'truncated' => $truncated,
        ]);
    }

    #[AsCallback(table: 'tl_import_from_csv', target: 'fields.importTable.options', priority: 100)]
    public function optionsCbGetTables(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->listTableNames();
    }

    #[AsCallback(table: 'tl_import_from_csv', target: 'fields.matchBy.options', priority: 100)]
    #[AsCallback(table: 'tl_import_from_csv', target: 'fields.skipValidationFields.options', priority: 100)]
    public function optionsCbGetTableColumns(DataContainer $dc): array
    {
        $tableName = $dc->activeRecord->importTable;

        if (!$tableName) {
            return [];
        }

        $schemaManager = $this->connection->createSchemaManager();

        // Get a list of all lowercase column names
        $lowerCaseFields = $schemaManager->listTableColumns($tableName);

        $this->framework->getAdapter(Controller::class)->loadDataContainer($tableName);

        $dcaFields = [];

        foreach (array_keys($GLOBALS['TL_DCA'][$tableName]['fields'] ?? []) as $k) {
            $dcaFields[strtolower($k)] = [
                'fieldName' => $k,
                'sql' => $GLOBALS['TL_DCA'][$tableName]['fields'][$k]['sql'] ?? null,
            ];
        }

        $arrOptions = [];

        foreach ($lowerCaseFields as $field) {
            $sql = $dcaFields[$field->getName()]['sql'] ?? '';
            $sql = \is_array($sql) ? json_encode($sql) : $sql;
            $sql = !empty($sql) ? \sprintf(' <span class="ifcb-sql-descr">[%s]</span>', $sql) : '';

            // If exists, take the column name from the DCA
            $fieldName = $dcaFields[$field->getName()]['fieldName'] ?? $field->getName();
            $arrOptions[$fieldName] = $fieldName.$sql;
        }

        return $arrOptions;
    }

    public function optionsCbGetCsvColumns(DataContainer|null $dc = null, bool $includeCustomFields = false): array
    {
        if (null === $dc || null === $dc->id) {
            return [];
        }

        $headers = [];

        $fileModel = $this->framework
            ->getAdapter(FilesModel::class)
            ->findOneBy(['uuid = ?'], [$dc->activeRecord->fileSRC])
        ;

        if ($fileModel) {
            $reader = $this->framework
                ->getAdapter(Reader::class)
                ->from(Path::join($this->projectDir, $fileModel->path), 'r')
            ;
            $reader->setHeaderOffset(0);
            $reader->setDelimiter($dc->getCurrentRecord()['fieldSeparator'] ?: ';');
            $headers = $reader->getHeader();
        }

        if (!empty($headers) && $includeCustomFields) {
            $newHeaders = [];

            foreach ($headers as $header) {
                $newHeaders[$header] = $header;
            }

            $headers = $newHeaders;
        }

        return $headers ?? [];
    }

    #[AsCallback(table: 'tl_import_from_csv', target: 'fields.selectedFields.load', priority: 100)]
    public function migrateSelectedFields(string $value, DataContainer $dc): mixed
    {
        if (!$dc->id) {
            return $value;
        }

        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $arrValue = $stringUtil->deserialize($value, true);

        if (!\is_array($arrValue[0])) {
            foreach ($arrValue as $k => $v) {
                $arrValue[$k] = [
                    'field_name' => $v,
                    'csv_field_name' => $v,
                ];
            }
        }

        return $arrValue;
    }
}
