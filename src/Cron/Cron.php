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

namespace Markocupic\ImportFromCsvBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvFactory;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Psr\Log\LoggerInterface;

class Cron
{
    public const string CRON_MINUTELY = 'minutely';

    public const string CRON_HOURLY = 'hourly';

    public const string CRON_DAILY = 'daily';

    public const string CRON_WEEKLY = 'weekly';

    public const string CRON_MONTHLY = 'monthly';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ImportFromCsvFactory $importFromCsvFactory,
        private readonly LoggerInterface|null $contaoCronLogger,
    ) {
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

        $importFromCsvModel = $this->framework->getAdapter(ImportFromCsvModel::class);
        $filesModel = $this->framework->getAdapter(FilesModel::class);

        if (null !== ($importModel = $importFromCsvModel->findBy(['enableCron = ?', 'cronLevel = ?'], ['1', $cronLevel]))) {
            while ($importModel->next()) {
                if (null !== ($file = $filesModel->findByUuid($importModel->fileSRC))) {
                    // Use helper class to launch the import process
                    if (null !== $this->importFromCsvFactory->createFromModel($importModel->current())) {
                        // Log new insert
                        $msg = \sprintf('Cron %s: Imported csv file "%s" into %s.', $cronLevel, $file->path, $importModel->importTable);
                        $this->contaoCronLogger->info($msg);
                    }
                }
            }
        }
    }
}
