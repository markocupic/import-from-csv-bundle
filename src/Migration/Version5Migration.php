<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Version5Migration extends AbstractMigration
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $doMigration = false;

        $schemaManager = $this->connection->getSchemaManager();

        // Version 5 migration: "Rename fields"
        // If the database table itself does not exist we should do nothing
        if ($schemaManager->tablesExist(['tl_import_from_csv'])) {
            $columns = $schemaManager->listTableColumns('tl_import_from_csv');

            $arrAlterations = $this->getAlterationData();

            foreach ($arrAlterations as $arrAlteration) {
                if (isset($columns[$arrAlteration['old']]) && !isset($columns[$arrAlteration['new']])) {
                    $doMigration = true;
                }
            }
        }

        return $doMigration;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $arrMessage = [];

        $schemaManager = $this->connection->getSchemaManager();

        // Version 5 migration: "Rename fields"
        if ($schemaManager->tablesExist(['tl_import_from_csv'])) {
            $columns = $schemaManager->listTableColumns('tl_import_from_csv');

            $arrAlterations = $this->getAlterationData();

            foreach ($arrAlterations as $arrAlteration) {
                if (isset($columns[$arrAlteration['old']]) && !isset($columns[$arrAlteration['new']])) {
                    $strQuery = sprintf(
                        'ALTER TABLE tl_import_from_csv CHANGE `%s` `%s` %s',
                        $arrAlteration['old'],
                        $arrAlteration['new'],
                        $arrAlteration['sql'],
                    );
                    $this->connection->query($strQuery);

                    $arrMessage[] = sprintf(
                        'Rename field tl_import_from_csv.%s to tl_import_from_csv.%s.',
                        $arrAlteration['old'],
                        $arrAlteration['new'],
                    );
                }
            }
        }

        return new MigrationResult(
            true,
            implode(' ', $arrMessage)
        );
    }

    private function getAlterationData(): array
    {
        return [
            [
                'old' => 'import_table',
                'new' => 'importTable',
                'sql' => 'varchar(255)',
            ],
            [
                'old' => 'field_separator',
                'new' => 'fieldSeparator',
                'sql' => 'varchar(255)',
            ],
            [
                'old' => 'field_enclosure',
                'new' => 'fieldEnclosure',
                'sql' => 'varchar(255)',
            ],
            [
                'old' => 'import_mode',
                'new' => 'importMode',
                'sql' => 'varchar(255)',
            ],
            [
                'old' => 'selected_fields',
                'new' => 'selectedFields',
                'sql' => 'blob',
            ],
        ];
    }
}
