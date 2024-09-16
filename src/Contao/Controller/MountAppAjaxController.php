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

namespace Markocupic\ImportFromCsvBundle\Contao\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Exception;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class MountAppAjaxController extends AbstractController
{
    private readonly Adapter $filesModel;
    private readonly Adapter $importFromCsvModel;
    private readonly Adapter $stringUtil;
    private readonly Adapter $reader;

    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly string $projectDir,
        private readonly string $csrfTokenName,
        private readonly int $perRequest,
    ) {
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->importFromCsvModel = $this->framework->getAdapter(ImportFromCsvModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->reader = $this->framework->getAdapter(Reader::class);
    }

    /**
     * @throws Exception
     */
    public function appMountAction(): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $request->query->get('token');
        $id = $request->query->get('id');
        $taskId = $request->query->get('taskId');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new \Exception('Invalid token!');
        }

        $objModel = $this->importFromCsvModel->findByPk($id);

        if (null === $objModel) {
            throw new \Exception('Import from csv model not found.');
        }

        $arrData['model'] = $objModel->row();

        $objFile = $this->filesModel->findByUuid($objModel->fileSRC);

        $arrData['model']['fileSRC'] = $objFile ? $objFile->path : '';
        $arrData['model']['selectedFields'] = $this->stringUtil->deserialize($objModel->selectedFields, true);
        $arrData['model']['skipValidationFields'] = $this->stringUtil->deserialize($objModel->skipValidationFields, true);

        $count = 0;
        $offset = (int) $objModel->offset;
        $limit = (int) $objModel->limit;

        if ($objFile) {
            $objCsvReader = $this->reader->createFromPath($this->projectDir.'/'.$objFile->path, 'r');
            $objCsvReader->setHeaderOffset(0);
            $count = (int) $objCsvReader->count();
        }

        $intRows = $offset > $count ? 0 : $count - $offset;

        if ($limit > 0) {
            $intRows = min($intRows, $limit);
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

            $arrUrl[] = $this->router->generate('contao_backend', [
                'do' => 'import_from_csv',
                'key' => 'importAction',
                'id' => $id,
                'taskId' => $taskId,
                'offset' => $offset + $i * $this->perRequest,
                'limit' => $limit,
                'req_num' => $i + 1,
                'token' => $token,
                'isTestMode' => '_isTestMode_',
            ]);
        }

        $arrData['model']['limit'] = $objModel->limit;
        $arrData['model']['offset'] = $objModel->offset;
        $arrData['model']['count'] = $count;
        $arrData['urlStack'] = $arrUrl;

        $json = ['data' => $arrData];

        $response = new JsonResponse($json);

        throw new ResponseException($response);
    }
}
