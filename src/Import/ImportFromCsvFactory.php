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

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\SyntaxError;
use League\Csv\UnavailableStream;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

readonly class ImportFromCsvFactory
{
    public function __construct(
        private ContaoFramework $framework,
        private ImportFromCsv $importFromCsv,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws UnavailableStream
     * @throws \Doctrine\DBAL\Exception
     */
    public function createFromModel(ImportFromCsvModel $model, bool $isTestMode = false, string|null $taskId = null): ImportFromCsv|null
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        $tableName = $model->importTable;
        $importMode = $model->importMode;
        $selectedFields = $stringUtil->deserialize($model->selectedFields, true);
        $mapValues = $stringUtil->deserialize($model->mapValues, true);
        $delimiter = $model->fieldSeparator;
        $matchBy = $model->matchBy;
        $enclosure = $model->fieldEnclosure;
        $offset = (int) $model->offset;
        $limit = (int) $model->limit;
        $skipValidationFields = $stringUtil->deserialize($model->skipValidationFields, true);
        $file = $this->framework
            ->getAdapter(FilesModel::class)
            ->findByUuid($model->fileSRC)
        ;

        // Call the import class if file exists
        if (!is_file(Path::join($this->projectDir, $file->path))) {
            return null;
        }

        $csvFile = new File($file->path);

        if ('csv' !== strtolower($csvFile->extension)) {
            return null;
        }

        $this->importFromCsv->importCsv(
            csvFile: $csvFile,
            tableName: $tableName,
            importMode: $importMode,
            selectedFields: $selectedFields,
            mapValues: $mapValues,
            delimiter: $delimiter,
            enclosure: $enclosure,
            arrayDelimiter: '||',
            isTestMode: $isTestMode,
            skipValidationFields: $skipValidationFields,
            offset: $offset,
            limit: $limit,
            taskId: $taskId,
            matchBy: $matchBy,
        );

        return $this->importFromCsv;
    }
}
