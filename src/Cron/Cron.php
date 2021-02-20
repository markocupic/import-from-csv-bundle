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

namespace Markocupic\ImportFromCsvBundle\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsv;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Cron
{
    const CRON_MINUTELY = 'minutely';
    const CRON_HOURLY = 'hourly';
    const CRON_DAILY = 'daily';
    const CRON_WEEKLY = 'weekly';
    const CRON_MONTHLY = 'monthly';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ImportFromCsv
     */
    private $importFromCsv;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(ContaoFramework $framework, ImportFromCsv $importFromCsv, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->importFromCsv = $importFromCsv;
        $this->logger = $logger;
    }

    public function initMinutely(): void
    {
        $this->initialize(static::CRON_MINUTELY);
    }

    public function initHourly(): void
    {
        $this->initialize(static::CRON_HOURLY);
    }

    public function initDaily(): void
    {
        $this->initialize(static::CRON_DAILY);
    }

    public function initWeekly(): void
    {
        $this->initialize(static::CRON_WEEKLY);
    }

    public function initMonthly(): void
    {
        $this->initialize(static::CRON_MONTHLY);
    }

    public function initialize(string $cronLevel): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        if (null !== ($objImportModel = ImportFromCsvModel::findBy(['enableCron = ?', 'cronLevel = ?'], ['1', $cronLevel]))) {
            while ($objImportModel->next()) {
                $strTable = $objImportModel->import_table;
                $importMode = $objImportModel->import_mode;
                $arrSelectedFields = $stringUtilAdapter->deserialize($objImportModel->selected_fields, true);
                $strDelimiter = $objImportModel->field_separator;
                $strEnclosure = $objImportModel->field_enclosure;
                $intOffset = (int) $objImportModel->offset;
                $intLimit = (int) $objImportModel->limit;
                $arrSkipValidationFields = $stringUtilAdapter->deserialize($objImportModel->skipValidationFields, true);
                $objFile = FilesModel::findByUuid($objImportModel->fileSRC);
                $blnTestMode = false;

                // call the import class if file exists
                if (is_file(TL_ROOT.'/'.$objFile->path)) {
                    $objFile = new File($objFile->path);

                    if ('csv' === strtolower($objFile->extension)) {
                        $this->importFromCsv->importCsv($objFile, $strTable, $importMode, $arrSelectedFields, $strDelimiter, $strEnclosure, '||', $blnTestMode, $arrSkipValidationFields, $intOffset, $intLimit);

                        // Log new insert
                        if (null !== $this->logger) {
                            $level = LogLevel::INFO;
                            $strText = sprintf('Cron %s: Imported csv file "%s" into %s.', $cronLevel, $objFile->path, $strTable);
                            $this->logger->log(
                                $level,
                                $strText, [
                                    'contao' => new ContaoContext(__METHOD__, $level),
                                ]);
                        }
                    }
                }
            }
        }
    }
}
