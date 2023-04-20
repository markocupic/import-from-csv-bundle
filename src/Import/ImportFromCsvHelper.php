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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportFromCsvHelper
{


    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly ImportFromCsv $importFromCsv,
        private readonly string $projectDir,
    )
    {

    }

    /**
     * @throws Exception
     */
    public function countRows(ImportFromCsvModel $model): int
    {
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $objFile = $filesModelAdapter->findByUuid($model->fileSRC);

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
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgument
     */
    public function importFromModel(ImportFromCsvModel $model, bool $isTestMode = false, string $taskId = null): bool
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $strTable = $model->importTable;
        $importMode = $model->importMode;
        $arrSelectedFields = $stringUtilAdapter->deserialize($model->selectedFields, true);
        $strDelimiter = $model->fieldSeparator;
        $strEnclosure = $model->fieldEnclosure;
        $intOffset = (int) $model->offset;
        $intLimit = (int) $model->limit;
        $arrSkipValidationFields = $stringUtilAdapter->deserialize($model->skipValidationFields, true);
        $objFile = $filesModelAdapter->findByUuid($model->fileSRC);

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
