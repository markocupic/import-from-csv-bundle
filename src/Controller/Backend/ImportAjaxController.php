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

namespace Markocupic\ImportFromCsvBundle\Controller\Backend;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvFactory;
use Markocupic\ImportFromCsvBundle\Logger\ImportLogger;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Security\Csrf\CsrfToken;

class ImportAjaxController extends AbstractController
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly ImportFromCsvFactory $importFromCsvFactory,
        private readonly ImportLogger $importLogger,
        private readonly RequestStack $requestStack,
        private readonly UriSigner $uriSigner,
        #[Autowire('%contao.csrf_token_name%')]
        private readonly string $csrfTokenName,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function importAction(): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        // POST-Request is signed and should be validated first.
        $this->validateRequest($request);

        $id = $request->query->get('id');
        $offset = $request->query->get('offset');
        $limit = $request->query->get('limit');
        $isTestMode = !('false' === $request->request->get('isTestMode'));
        $taskId = $request->query->get('taskId');

        $this->importLogger->initialize($taskId);

        if (null !== ($importModel = $this->framework->getAdapter(ImportFromCsvModel::class)->findById($id))) {
            if (null !== $this->framework->getAdapter(FilesModel::class)->findByUuid($importModel->fileSRC)) {
                $importModel->offset = $offset;
                $importModel->limit = $limit;

                if ((int) $request->query->get('req_num') > 1) {
                    $importModel->importMode = 'append_entries';
                }

                // Use helper class to launch the import process
                if (null !== $this->importFromCsvFactory->createFromModel($importModel->current(), $isTestMode, $taskId)) {
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

    private function validateRequest(Request|null $request): void
    {
        if (null === $request) {
            throw new \RuntimeException('No HTTP request available for validation.');
        }

        if (Request::METHOD_POST !== $request->getMethod()) {
            throw new \RuntimeException('Invalid HTTP method: expected POST.');
        }

        if (!$this->uriSigner->checkRequest($request)) {
            throw new \InvalidArgumentException('The request signature is invalid or has been tampered with.');
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $request->request->get('REQUEST_TOKEN')))) {
            throw new InvalidRequestTokenException('Invalid CSRF token!');
        }
    }
}
