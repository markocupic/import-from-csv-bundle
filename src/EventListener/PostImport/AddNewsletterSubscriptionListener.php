<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\EventListener\PostImport;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ImportFromCsvBundle\Event\PostImportEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PostImportEvent::NAME, method: 'addNewsletterSubscription')]
final class AddNewsletterSubscriptionListener
{
    private Adapter $stringUtil;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    public function addNewsletterSubscription(PostImportEvent $event): void
    {
        if ('tl_member' !== $event->getTableName()) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM tl_member WHERE id = ?', [$event->getInsertId()]);

        if (false === $row) {
            return;
        }

        if (!empty($row['newsletter']) && !empty($row['email'])) {
            $this->addMemberToNewsletterRecipientList($row, $event);
        }
    }

    private function addMemberToNewsletterRecipientList(array $row, PostImportEvent $event): void
    {
        $newsletters = $this->stringUtil->deserialize($row['newsletter'], true);

        $importInstance = $event->getImportInstance();

        foreach ($newsletters as $newsletterId) {
            $id = $this->connection->fetchOne(
                'SELECT id FROM tl_newsletter_recipients WHERE email LIKE ? AND pid = ?',
                [
                    $row['email'],
                    $newsletterId,
                ]
            );

            if (!$id) {
                $set = [
                    'tstamp' => time(),
                    'pid' => $newsletterId,
                    'email' => $row['email'],
                    'active' => '1',
                ];

                try {
                    $this->connection->beginTransaction();
                    $this->connection->insert('tl_newsletter_recipients', $set);
                    $this->connection->commit();
                } catch (\Exception $e) {
                    $this->connection->rollBack();
                    $importInstance->addInsertException($e);
                }
            }
        }
    }
}
