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
use Contao\FilesModel;
use League\Csv\Exception;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

readonly class ImportFromCsvHelper
{
    public function __construct(
        private ContaoFramework $framework,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @throws Exception
     */
    public function countRows(ImportFromCsvModel $importModel): int
    {
        $fileModel = $this->framework
            ->getAdapter(FilesModel::class)
            ->findByUuid($importModel->fileSRC)
        ;

        if (null !== $fileModel) {
            $reader = $this->framework
                ->getAdapter(Reader::class)
                ->from(Path::join($this->projectDir, $fileModel->path), 'r')
            ;
            $reader->setHeaderOffset(0);
            $count = $reader->count();
            $count -= (int) $importModel->offset;
            $limit = (int) $importModel->limit;

            if ($count < 1) {
                return 0;
            }

            if (0 === $limit || $limit > $count) {
                return $count;
            }

            return $limit;
        }

        return 0;
    }
}
