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

namespace Markocupic\ImportFromCsvBundle\Import;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;

/**
 * Class ImportFromCsv.
 */
class ImportFromCsvHelper
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ImportFromCsv
     */
    private $importFromCsv;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(ContaoFramework $framework, ImportFromCsv $importFromCsv, string $projectDir)
    {
        $this->framework = $framework;
        $this->importFromCsv = $importFromCsv;
        $this->projectDir = $projectDir;
    }

    public function importFromModel(ImportFromCsvModel $model, bool $isTestMode = false): bool
    {
        /** @var \StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $strTable = $model->import_table;
        $importMode = $model->import_mode;
        $arrSelectedFields = $stringUtilAdapter->deserialize($model->selected_fields, true);
        $strDelimiter = $model->field_separator;
        $strEnclosure = $model->field_enclosure;
        $intOffset = (int) $model->offset;
        $intLimit = (int) $model->limit;
        $arrSkipValidationFields = $stringUtilAdapter->deserialize($model->skipValidationFields, true);
        $objFile = $filesModelAdapter->findByUuid($model->fileSRC);

        // Call the import class if file exists
        if (is_file($this->projectDir.'/'.$objFile->path)) {
            $objFile = new File($objFile->path);

            if ('csv' === strtolower($objFile->extension)) {
                $this->importFromCsv->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strDelimiter, $strEnclosure, '||', $isTestMode, $arrSkipValidationFields, $intOffset, $intLimit);
                return true;
            }
        }

        return false;
    }
}
