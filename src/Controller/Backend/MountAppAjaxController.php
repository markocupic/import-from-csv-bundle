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
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use League\Csv\Exception;
use League\Csv\Reader;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\TranslatorInterface;

class MountAppAjaxController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
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
        $csrfToken = $request->query->get('csrf_token');
        $id = $request->query->get('id');
        $taskId = $request->query->get('taskId');

        $this->validateCsrfToken($csrfToken);

        $importModel = $this->framework
            ->getAdapter(ImportFromCsvModel::class)
            ->findById($id)
        ;

        if (null === $importModel) {
            throw new \Exception('Import from csv model not found.');
        }
        $arrData = [];

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

        try {
            if (null === $file) {
                $msg = $this->translator->trans('tl_import_from_csv.exception\.csv_file_not_found', [], 'contao_default');

                throw new \Exception($msg);
            }

            $reader = $this->framework
                ->getAdapter(Reader::class)
                ->from(Path::join($this->projectDir, $file->path), 'r')
            ;
            $reader->setHeaderOffset(0);
            $count = (int) $reader->count();

            // Validate the table name
            $this->validateTable($importModel->importTable);

            // Check if the selected fields exist in the header of the csv file
            $this->validateSelectedFields($arrData['model']['selectedFields'], $reader->getHeader(), $importModel->importTable);
        } catch (\Exception $e) {
            $arrData['error'] = $e->getMessage();
            $json = ['data' => $arrData];

            throw new ResponseException(new JsonResponse($json));
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

            $url = $this->router->generate(
                'contao_backend',
                [
                    'do' => 'import_from_csv',
                    'key' => 'importAction',
                    'id' => $id,
                    'taskId' => $taskId,
                    'offset' => $offset + $i * $this->perRequest,
                    'limit' => $limit,
                    'req_num' => $i + 1,
                    'req_total' => $countRequests,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $url = $this->uriSigner->sign($url);

            $arrUrl[] = $url;
        }

        $arrData['model']['limit'] = $importModel->limit;
        $arrData['model']['offset'] = $importModel->offset;
        $arrData['model']['count'] = $count;
        $arrData['urlStack'] = $arrUrl;

        $json = ['data' => $arrData];

        $response = new JsonResponse($json);

        throw new ResponseException($response);
    }

    public function tableExists(string $tableName): bool
    {
        $sm = $this->connection->createSchemaManager();

        return $sm->tablesExist([$tableName]);
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $sm = $this->connection->createSchemaManager();

        return $sm->tablesExist([$tableName]) && isset($sm->listTableColumns($tableName)[strtolower($columnName)]);
    }

    private function validateCsrfToken(string $token): void
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new InvalidRequestTokenException('Invalid CSRF token!');
        }
    }

    private function validateTable(string $tableName): void
    {
        if (!$this->tableExists($tableName)) {
            throw new \Exception($this->translator->trans('tl_import_from_csv.exception\.table_not_found', [$tableName], 'contao_default'));
        }
    }

    private function validateSelectedFields(array $selectedFields, array $csvHeaderFields, string $tableName): void
    {
        $mapping = [];

        foreach ($selectedFields as $item) {
            $mapping[$item['field_name']] = $item['csv_field_name'];
            if (!\in_array($item['csv_field_name'], $csvHeaderFields, true)) {
                $msg = $this->translator->trans('tl_import_from_csv.exception\.invalid_field_mapping', [$item['csv_field_name']], 'contao_default');

                throw new \Exception($msg);
            }

            if (!$this->columnExists($tableName, $item['field_name'])) {
                $msg = $this->translator->trans('tl_import_from_csv.exception\.column_not_found_in_table', [$item['field_name'], $tableName], 'contao_default');

                throw new \Exception($msg);
            }
        }
    }
}
