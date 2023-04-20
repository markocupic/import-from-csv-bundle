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

namespace Markocupic\ImportFromCsvBundle\Contao\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\FilesModel;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvHelper;
use Markocupic\ImportFromCsvBundle\Logger\ImportLogger;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;

class ImportAjaxController extends AbstractController
{


    public function __construct(
        private readonly ImportFromCsvHelper $importFromCsvHelper,
        private readonly ContaoFramework $framework,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TokenChecker $tokenChecker,
        private readonly RequestStack $requestStack,
        private readonly ImportLogger $importLogger,
        private readonly string $csrfTokenName,
        )
    {

    }

    /**
     * @throws \Exception
     */
    public function importAction(): JsonResponse
    {
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $request = $this->requestStack->getCurrentRequest();
        $token = $request->query->get('token');
        $id = $request->query->get('id');
        $offset = $request->query->get('offset');
        $limit = $request->query->get('limit');
        $isTestMode = !('false' === $request->query->get('isTestMode'));
        $taskId = $request->query->get('taskId');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new \Exception('Invalid token!');
        }

        $this->importLogger->initialize($taskId);

        if (null !== ($objImportModel = $importFromCsvModelAdapter->findByPk($id))) {
            if (null !== $filesModelAdapter->findByUuid($objImportModel->fileSRC)) {
                $objImportModel->offset = $offset;
                $objImportModel->limit = $limit;

                if ((int) $request->query->get('req_num') > 1) {
                    $objImportModel->importMode = 'append_entries';
                }

                // Use helper class to launch the import process
                if (true === $this->importFromCsvHelper->importFromModel($objImportModel->current(), $isTestMode, $taskId)) {
                    $arrData = [];
                    $arrData['data'] = $this->importLogger->getLog($taskId);

                    $response = new JsonResponse($arrData);

                    throw new ResponseException($response);
                }
            }
        }

        $arrData = [];
        $arrData['data'] = $this->importLogger->getLog($taskId);

        $response = new JsonResponse($arrData);

        throw new ResponseException($response);
    }
}
