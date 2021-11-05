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

namespace Markocupic\ImportFromCsvBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvHelper;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(defaults={"_scope" = "backend", "_token_check" = true })
 */
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
     * @var TranslatorInterface
     */
    private $translator;

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
    private $projectDir;

    /**
     * @var string
     */
    private $csrfTokenName;

    /**
     * @var int
     */
    private $perRequest;

    public function __construct(ImportFromCsvHelper $importFromCsvHelper, ContaoFramework $framework, TranslatorInterface $translator, ContaoCsrfTokenManager $csrfTokenManager, TokenChecker $tokenChecker, RequestStack $requestStack, string $projectDir, string $csrfTokenName, int $perRequest)
    {
        $this->importFromCsvHelper = $importFromCsvHelper;
        $this->framework = $framework;
        $this->translator = $translator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->tokenChecker = $tokenChecker;
        $this->requestStack = $requestStack;
        $this->projectDir = $projectDir;
        $this->csrfTokenName = $csrfTokenName;
        $this->perRequest = $perRequest;
    }

    /**
     * @Route("/contao/csv_import", name="markocupic_csv_import")
     */
    public function csvImport(): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $request->query->get('token');
        $id = $request->query->get('id');
        $offset = $request->query->get('offset');
        $limit = $request->query->get('limit');
        $isTestMode = 'false' === $request->query->get('isTestMode') ? false : true;

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new \Exception('Invalid token!');
        }

        /** @var ImportFromCsvModel $importFromCsvModelAdapter */
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        if (null !== ($objImportModel = $importFromCsvModelAdapter->findByPk($id))) {
            if (null !== $filesModelAdapter->findByUuid($objImportModel->fileSRC)) {
                $objImportModel->offset = $offset;
                $objImportModel->limit = $limit;
                // Use helper class to launch the import process
                if (true === $this->importFromCsvHelper->importFromModel($objImportModel->current(), $isTestMode)) {
                    $arrData = [];
                    $arrData['data'] = $this->importFromCsvHelper->getReport();

                    return new JsonResponse($arrData);
                }
            }
        }

        $arrData = [];
        $arrData['data'] = $this->importFromCsvHelper->getReport();

        return new JsonResponse($arrData);
    }

    /**
     * @Route("/contao/get_model_data", name="markocupic_csv_import_get_model_data")
     */
    public function getModelData(): JsonResponse
    {
        $this->framework->initialize(false);

        $request = $this->requestStack->getCurrentRequest();
        $token = $request->query->get('token');
        $id = $request->query->get('id');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new \Exception('Invalid token!');
        }

        $this->framework->initialize(false);
        $objModel = ImportFromCsvModel::findByPk($id);

        if (null === $objModel) {
            throw new \Exception('Import from csv model not found.');
        }

        $arrData['model'] = $objModel->row();

        $objFile = FilesModel::findByUuid($objModel->fileSRC);
        $arrData['model']['fileSRC'] = $objFile ? $objFile->path : '';
        $arrData['model']['selected_fields'] = StringUtil::deserialize($objModel->selected_fields, true);
        $arrData['model']['skipValidationFields'] = StringUtil::deserialize($objModel->skipValidationFields, true);

        $count = 0;
        $offset = (int) $objModel->offset;
        $intRows = (int) $objModel->limit;

        if ($objFile) {
            $objCsvReader = Reader::createFromPath($this->projectDir.'/'.$objFile->path, 'r');
            $objCsvReader->setHeaderOffset(0);
            $count = $objCsvReader->count();
        }

        if ($count - $offset < $intRows) {
            $intRows = $count - $offset;
        }

        if ($count - $offset < $intRows) {
            $intRows = $count - $offset;
        }

        $arrUrl = [];
        $countRequests = ceil($intRows / $this->perRequest);

        for ($i = 0; $i < $countRequests; ++$i) {
            $pending = $intRows - $i * $this->perRequest;

            if ($pending < $this->perRequest) {
                $limit = $pending;
            } else {
                $limit = $this->perRequest;
            }

            $arrUrl[] = sprintf(
                'contao/csv_import?id=%s&offset=%s&limit=%s&token=%s',
                $id,
                $offset + $i * $this->perRequest,
                $limit,
                $token,
            );
        }

        $arrData['model']['limit'] = $objModel->limit;
        $arrData['model']['offset'] = $objModel->offset;
        $arrData['model']['count'] = $count;
        $arrData['urlStack'] = $arrUrl;

        $json = ['data' => $arrData];

        return new JsonResponse($json);
    }
}
