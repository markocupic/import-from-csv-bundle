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

use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use League\Csv\Exception;
use Markocupic\ImportFromCsvBundle\Import\ImportFromCsvHelper;
use Markocupic\ImportFromCsvBundle\Model\ImportFromCsvModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RenderBackendAppController
{
    private readonly Adapter $controller;

    private readonly Adapter $importFromCsvModel;

    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly ImportFromCsvHelper $importFromCsvHelper,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TwigEnvironment $twig,
    ) {
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->importFromCsvModel = $this->framework->getAdapter(ImportFromCsvModel::class);
    }

    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderAppAction(DataContainer $dc): Response
    {
        // Load language file
        $this->controller->loadLanguageFile('tl_import_from_csv');

        $model = $this->importFromCsvModel->findById($dc->id);

        return new Response($this->twig->render(
            '@MarkocupicImportFromCsv/import.html.twig',
            [
                'backHref' => $this->router->generate('contao_backend', [
                    'do' => 'import_from_csv',
                ]),
                'editHref' => $this->router->generate('contao_backend', [
                    'do' => 'import_from_csv',
                    'act' => 'edit',
                    'id' => $dc->id,
                    'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
                ]),
                'model' => $model->row(),
                'head' => [
                    'countRows' => $this->importFromCsvHelper->countRows($model),
                ],
                'lang' => [
                    'MSC' => $GLOBALS['TL_LANG']['MSC'],
                    'tl_import_from_csv' => $GLOBALS['TL_LANG']['tl_import_from_csv'],
                ],
                'form' => [
                    'action' => $this->requestStack->getCurrentRequest()->getUri(),
                    'input' => [
                        'id' => $dc->id,
                    ],
                    'csrfToken' => $this->csrfTokenManager->getDefaultTokenValue(),
                ],
                'appMountUrl' => $this->router->generate('contao_backend', [
                    'do' => 'import_from_csv',
                    'key' => 'appMountAction',
                    'id' => $dc->id,
                    'taskId' => uniqid(),
                    'csrf_token' => $this->csrfTokenManager->getDefaultTokenValue(),
                ]),
                'csrfToken' => $this->csrfTokenManager->getDefaultTokenValue(),
            ],
        ));
    }
}
