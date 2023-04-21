<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
        $strTable = $model->importTable;
        $importMode = $model->importMode;
        $arrSelectedFields = $this->stringUtil->deserialize($model->selectedFields, true);
        $strDelimiter = $model->fieldSeparator;
        $strEnclosure = $model->fieldEnclosure;
        $intOffset = (int) $model->offset;
        $intLimit = (int) $model->limit;
        $arrSkipValidationFields = $this->stringUtil->deserialize($model->skipValidationFields, true);
        $objFile = $this->filesModel->findByUuid($model->fileSRC);

        // Call the import class if file exists
        if (is_file($this->projectDir.'/'.$objFile->path)) {
            $objFile = new File($objFile->path);

            if ('csv' === strtolower($objFile->extension)) {
                $this->importFromCsv->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strDelimiter, $strEnclosure, '||', $isTestMode, $arrSkipValidationFields, $intOffset, $intLimit, $taskId);

                return true;
            }
        }

        return false;
    }
}
