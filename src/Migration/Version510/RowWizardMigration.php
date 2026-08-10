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

namespace Markocupic\ImportFromCsvBundle\Migration\Version510;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

class RowWizardMigration extends AbstractMigration
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_import_from_csv'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_import_from_csv');

        if (!isset($columns['selectedfields']) || !isset($columns['id'])) {
            return false;
        }

        $rows = $this->connection->fetchAllAssociative("
            SELECT
                id
            FROM
                tl_import_from_csv
            WHERE
                selectedFields LIKE 'a:%'
            AND
                selectedFields NOT LIKE '%field_name%'
            AND
                selectedFields NOT LIKE '%csv_field_name%'
        ");

        if (empty($rows)) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllKeyValue("
            SELECT
                id, selectedFields
            FROM
                tl_import_from_csv
            WHERE
                selectedFields LIKE 'a:%'
            AND
                selectedFields NOT LIKE '%field_name%'
            AND
                selectedFields NOT LIKE '%csv_field_name%'
          ");

        foreach ($rows as $id => $selectedFields) {
            $this->updateField($id, $selectedFields);
        }

        return new MigrationResult(
            true,
            'Successfully migrated the tl_import_from_csv.selectedFields value to the new format.',
        );
    }

    private function updateField(int $id, string $selectedFields): void
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $arrValue = $stringUtil->deserialize($selectedFields, true);

        if (empty($arrValue)) {
            $this->connection->update(
                'tl_import_from_csv',
                [
                    'selectedFields' => null,
                ],
                [
                    'id' => $id,
                ],
                [
                    Types::INTEGER,
                ],
            );

            return;
        }

        $migratedValue = [];

        if (!\is_array($arrValue[0])) {
            foreach ($arrValue as $v) {
                $migratedValue[] = [
                    'field_name' => $v,
                    'csv_field_name' => $v,
                ];
            }
        }

        $this->connection->update(
            'tl_import_from_csv',
            [
                'selectedFields' => serialize($migratedValue),
            ],
            [
                'id' => $id,
            ],
            [
                Types::INTEGER,
            ],
        );
    }
}
