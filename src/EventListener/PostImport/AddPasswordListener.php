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

namespace Markocupic\ImportFromCsvBundle\EventListener\PostImport;

use Contao\BackendUser;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ImportFromCsvBundle\Event\PostImportEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsEventListener(event: PostImportEvent::NAME, method: 'addPassword')]
final class AddPasswordListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * @throws Exception
     */
    public function addPassword(PostImportEvent $event): void
    {
        return;

        $tableName = $event->getTableName();
        $importInstance = $event->getImportInstance();
        $arrLine = $event->getLineAsArray();
        $insertId = $event->getInsertId();

        if (!empty($arrLine['password']) && \is_string($arrLine['password'])) {
            if ('tl_member' === $tableName) {
                $hash = $this->passwordHasherFactory->getPasswordHasher(FrontendUser::class)->hash($arrLine['password']);
            } elseif ('tl_user' === $tableName) {
                $hash = $this->passwordHasherFactory->getPasswordHasher(BackendUser::class)->hash($arrLine['password']);
            }

            if (!empty($hash)) {
                $set = ['password' => $hash];

                try {
                    $this->connection->beginTransaction();
                    $this->connection->update($tableName, $set, ['id' => $insertId]);
                    $this->connection->commit();
                } catch (\Exception $e) {
                    $importInstance->addInsertException($e);
                    $this->connection->rollBack();
                }
            }
        }
    }
}
