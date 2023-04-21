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

namespace Markocupic\ImportFromCsvBundle\Logger;

use Markocupic\ImportFromCsvBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

class ImportLogger
{
    public const LOG_LEVEL_FAILURE = 'failure';
    public const LOG_LEVEL_SUCCESS = 'success';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function initialize(string $taskId = ''): string
    {
        $taskId = !\strlen($taskId) ? uniqid() : $taskId;

        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag(ArrayAttributeBag::ATTRIBUTE_NAME);

        $dataAll = $bag->all();

        $dataAll[$taskId] = [
            'logs' => [],
            'summary' => [
                'rows' => 0,
                'success' => 0,
                'errors' => 0,
            ],
        ];

        $bag->replace($dataAll);

        return $taskId;
    }

    public function hasInitialized(string $taskId): bool
    {
        return null !== $this->getData($taskId);
    }

    public function log(string $taskId, string $log): void
    {
        $arrData = $this->getData($taskId);

        $arrData['logs'] .= $log;

        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag(ArrayAttributeBag::ATTRIBUTE_NAME);

        $dataAll = $bag->all();
        $dataAll[$taskId] = $arrData;

        $bag->replace($dataAll);
    }

    public function addFailure(string $taskId, int $line, string $text, array $values): void
    {
        $this->addLogItem($taskId, self::LOG_LEVEL_FAILURE, $line, $text, $values);
    }

    public function addSuccess(string $taskId, int $line, string $text, array $values): void
    {
        $this->addLogItem($taskId, self::LOG_LEVEL_SUCCESS, $line, $text, $values);
    }

    public function setSummaryData(string $taskId, int $intTotal, int $intSuccess, int $intFailure): void
    {
        $arrData = $this->getData($taskId);

        $arrData['summary'] = [
            'rows' => $intTotal,
            'success' => $intSuccess,
            'errors' => $intFailure,
        ];

        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag(ArrayAttributeBag::ATTRIBUTE_NAME);

        $dataAll = $bag->all();
        $dataAll[$taskId] = $arrData;

        $bag->replace($dataAll);
    }

    public function getLog(string $taskId): array|null
    {
        return $this->getData($taskId);
    }

    private function addLogItem(string $taskId, string $type, int $line, string $text, array $values): void
    {
        $arrData = $this->getData($taskId);

        $arrData['logs'][] = [
            'type' => $type,
            'line' => $line,
            'text' => $text,
            'values' => $values,
        ];

        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag(ArrayAttributeBag::ATTRIBUTE_NAME);

        $dataAll = $bag->all();
        $dataAll[$taskId] = $arrData;

        $bag->replace($dataAll);
    }

    private function getData(string $taskId): array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $session = $request->getSession();

        /** @var AttributeBagInterface $bag */
        $bag = $session->getBag(ArrayAttributeBag::ATTRIBUTE_NAME);

        $dataAll = $bag->all();

        return $dataAll[$taskId] ?? null;
    }
}
