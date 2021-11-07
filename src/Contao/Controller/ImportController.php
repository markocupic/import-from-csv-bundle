<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
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
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;

class ImportController extends AbstractController
{
    /**
     * @var ImportFromCsvHelper
     */
    private $importFromCsvHelper;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ContaoCsrfTokenManager
     */
    private $csrfTokenManager;

    /**
     * @var TokenChecker
     */
    private $tokenChecker;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $csrfTokenName;

    public function __construct(ImportFromCsvHelper $importFromCsvHelper, ContaoFramework $framework, ContaoCsrfTokenManager $csrfTokenManager, TokenChecker $tokenChecker, RequestStack $requestStack, string $csrfTokenName)
    {
        $this->importFromCsvHelper = $importFromCsvHelper;
        $this->framework = $framework;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->tokenChecker = $tokenChecker;
        $this->requestStack = $requestStack;
        $this->csrfTokenName = $csrfTokenName;
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
        $isTestMode = 'false' === $request->query->get('isTestMode') ? false : true;

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new \Exception('Invalid token!');
        }



        if (null !== ($objImportModel = $importFromCsvModelAdapter->findByPk($id))) {
            if (null !== $filesModelAdapter->findByUuid($objImportModel->fileSRC)) {
                $objImportModel->offset = $offset;
                $objImportModel->limit = $limit;
                // Use helper class to launch the import process
                if (true === $this->importFromCsvHelper->importFromModel($objImportModel->current(), $isTestMode)) {
                    $arrData = [];
                    $arrData['data'] = $this->importFromCsvHelper->getReport();

                    $response = new JsonResponse($arrData);
                    throw new ResponseException($response);
                }
            }
        }

        $arrData = [];
        $arrData['data'] = $this->importFromCsvHelper->getReport();

        $response = new JsonResponse($arrData);
        throw new ResponseException($response);
    }
}
