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

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\SyntaxError;
use League\Csv\UnavailableStream;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\Filesystem\Path;

class ImportFromCsvHelper
{
    private readonly Adapter $filesModel;

    private readonly Adapter $stringUtil;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ImportFromCsv $importFromCsv,
        private readonly string $projectDir,
    ) {
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * @throws Exception
     */
    public function countRows(ImportFromCsvModel $model): int
    {
        $objFile = $this->filesModel->findByUuid($model->fileSRC);

        if ($objFile) {
            $objCsvReader = Reader::createFromPath($this->projectDir.'/'.$objFile->path, 'r');
            $objCsvReader->setHeaderOffset(0);
            $count = $objCsvReader->count();
            $count -= (int) $model->offset;
            $limit = (int) $model->limit;

            if ($count < 1) {
                return 0;
            }

            if ($limit > $count) {
                return $count;
            }

            return $limit;
        }

        return 0;
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws UnavailableStream
     * @throws \Doctrine\DBAL\Exception
     */
    public function importFromModel(ImportFromCsvModel $model, bool $isTestMode = false, string|null $taskId = null): bool
    {
        $tableName = $model->importTable;
        $importMode = $model->importMode;
        $selectedFields = $this->stringUtil->deserialize($model->selectedFields, true);
        $delimiter = $model->fieldSeparator;
        $enclosure = $model->fieldEnclosure;
        $offset = (int) $model->offset;
        $limit = (int) $model->limit;
        $skipValidationFields = $this->stringUtil->deserialize($model->skipValidationFields, true);
        $file = $this->filesModel->findByUuid($model->fileSRC);

        // Call the import class if file exists
        if (is_file(Path::join($this->projectDir, $file->path))) {
            $csvFile = new File($file->path);

            if ('csv' === strtolower($csvFile->extension)) {
                $this->importFromCsv->importCsv(
                    csvFile: $csvFile,
                    tableName: $tableName,
                    importMode: $importMode,
                    selectedFields: $selectedFields,
                    delimiter: $delimiter,
                    enclosure: $enclosure,
                    arrayDelimiter: '||',
                    isTestMode: $isTestMode,
                    skipValidationFields: $skipValidationFields,
                    offset: $offset,
                    limit: $limit,
                    taskId: $taskId,
                );

                return true;
            }
        }

        return false;
    }
}
