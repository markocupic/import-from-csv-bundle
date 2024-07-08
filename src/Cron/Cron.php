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

namespace Markocupic\ImportFromCsvBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvHelper;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Psr\Log\LoggerInterface;

class Cron
{
    public const CRON_MINUTELY = 'minutely';
    public const CRON_HOURLY = 'hourly';
    public const CRON_DAILY = 'daily';
    public const CRON_WEEKLY = 'weekly';
    public const CRON_MONTHLY = 'monthly';

    private readonly Adapter $importFromCsvModel;
    private readonly Adapter $filesModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ImportFromCsvHelper $importFromCsvHelper,
        private readonly LoggerInterface|null $contaoCronLogger,
    ) {
        $this->importFromCsvModel = $this->framework->getAdapter(ImportFromCsvModel::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
    }

    #[AsCronJob(self::CRON_MINUTELY)]
    public function initMinutely(): void
    {
        $this->initialize(static::CRON_MINUTELY);
    }

    #[AsCronJob(self::CRON_HOURLY)]
    public function initHourly(): void
    {
        $this->initialize(static::CRON_HOURLY);
    }

    #[AsCronJob(self::CRON_DAILY)]
    public function initDaily(): void
    {
        $this->initialize(static::CRON_DAILY);
    }

    #[AsCronJob(self::CRON_WEEKLY)]
    public function initWeekly(): void
    {
        $this->initialize(static::CRON_WEEKLY);
    }

    #[AsCronJob(self::CRON_MONTHLY)]
    public function initMonthly(): void
    {
        $this->initialize(static::CRON_MONTHLY);
    }

    public function initialize(string $cronLevel): void
    {
        // Initialize Contao framework
        $this->framework->initialize();

        if (null !== ($objImportModel = $this->importFromCsvModel->findBy(['enableCron = ?', 'cronLevel = ?'], ['1', $cronLevel]))) {
            while ($objImportModel->next()) {
                $strTable = $objImportModel->importTable;

                if (null !== ($objFile = $this->filesModel->findByUuid($objImportModel->fileSRC))) {
                    // Use helper class to launch the import process
                    if (true === $this->importFromCsvHelper->importFromModel($objImportModel->current())) {
                        // Log new insert
                        if (null !== $this->contaoCronLogger) {
                            $strText = sprintf('Cron %s: Imported csv file "%s" into %s.', $cronLevel, $objFile->path, $strTable);
                            $this->contaoCronLogger->info($strText);
                        }
                    }
                }
            }
        }
    }
}
