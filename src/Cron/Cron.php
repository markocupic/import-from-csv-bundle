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
use Contao\FilesModel;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvHelper;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Cron
{
    public const CRON_MINUTELY = 'minutely';
    public const CRON_HOURLY = 'hourly';
    public const CRON_DAILY = 'daily';
    public const CRON_WEEKLY = 'weekly';
    public const CRON_MONTHLY = 'monthly';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ImportFromCsvHelper
     */
    private $importFromCsvHelper;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(ContaoFramework $framework, ImportFromCsvHelper $importFromCsvHelper, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->importFromCsvHelper = $importFromCsvHelper;
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
        /** @var ImportFromCsvModel $importFromCsvModelAdapter */
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        if (null !== ($objImportModel = $importFromCsvModelAdapter->findBy(['enableCron = ?', 'cronLevel = ?'], ['1', $cronLevel]))) {
            while ($objImportModel->next()) {
                $strTable = $objImportModel->import_table;

                if (null !== ($objFile = $filesModelAdapter->findByUuid($objImportModel->fileSRC))) {

                    // Use helper class to launch the import process
                    if (true === $this->importFromCsvHelper->importFromModel($objImportModel->current())) {
                        // Log new insert
                        if (null !== $this->logger) {
                            $level = LogLevel::INFO;
                            $strText = sprintf('Cron %s: Imported csv file "%s" into %s.', $cronLevel, $objFile->path, $strTable);
                            $this->logger->log(
                                $level,
                                $strText,
                                [
                                    'contao' => new ContaoContext(__METHOD__, $level),
                                ]
                            );
                        }
                    }
                }
            }
        }
    }
}
