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

namespace Markocupic\ImportFromCsvBundle\Contao\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use League\Csv\Exception;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class MountAppAjaxController extends AbstractController
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%contao.csrf_token_name%')]
        private readonly string $csrfTokenName,
        #[Autowire('%markocupic_import_from_csv.max_inserts_per_request%')]
        private readonly int $perRequest,
    ) {
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

        $importModel = $this->framework
            ->getAdapter(ImportFromCsvModel::class)
            ->findById($id)
        ;

        if (null === $importModel) {
            throw new \Exception('Import from csv model not found.');
        }

        $arrData['model'] = $importModel->row();

        $file = $this->framework
            ->getAdapter(FilesModel::class)
            ->findByUuid($importModel->fileSRC)
        ;

        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        $arrData['model']['fileSRC'] = null !== $file ? $file->path : '';
        $arrData['model']['selectedFields'] = $stringUtil->deserialize($importModel->selectedFields, true);
        $arrData['model']['skipValidationFields'] = $stringUtil->deserialize($importModel->skipValidationFields, true);

        $count = 0;
        $offset = (int) $importModel->offset;
        $limit = (int) $importModel->limit;

        if (null !== $file) {
            $reader = $this->framework
                ->getAdapter(Reader::class)
                ->from(Path::join($this->projectDir, $file->path), 'r')
            ;
            $reader->setHeaderOffset(0);
            $count = (int) $reader->count();
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

        $arrData['model']['limit'] = $importModel->limit;
        $arrData['model']['offset'] = $importModel->offset;
        $arrData['model']['count'] = $count;
        $arrData['urlStack'] = $arrUrl;

        $json = ['data' => $arrData];

        $response = new JsonResponse($json);

        throw new ResponseException($response);
    }
}
