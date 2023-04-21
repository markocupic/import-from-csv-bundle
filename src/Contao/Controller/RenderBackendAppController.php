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
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RenderBackendAppController
{
    private readonly Adapter $controller;
    private readonly Adapter $importFromCsvModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ImportFromCsvHelper $importFromCsvHelper,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TwigEnvironment $twig,
        private readonly RequestStack $requestStack,
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

        $request = $this->requestStack->getCurrentRequest();
        $model = $this->importFromCsvModel->findByPk($dc->id);
        $arrData = $model->row();

        $csrfToken = $this->csrfTokenManager->getDefaultTokenValue();

        return new Response($this->twig->render(
            '@MarkocupicImportFromCsv/import.html.twig',
            [
                'backHref' => 'contao?do=import_from_csv',
                'editHref' => sprintf('contao?do=import_from_csv&act=edit&id=%s&rt=%s', $dc->id, $csrfToken),
                'model' => $arrData,
                'head' => [
                    'countRows' => $this->importFromCsvHelper->countRows($model),
                ],
                'lang' => [
                    'MSC' => $GLOBALS['TL_LANG']['MSC'],
                    'tl_import_from_csv' => $GLOBALS['TL_LANG']['tl_import_from_csv'],
                ],
                'form' => [
                    'action' => $request->getUri(),
                    'input' => [
                        'id' => $request->query->get('id'),
                        'taskId' => uniqid(),
                        'csrfToken' => $csrfToken,
                    ],
                ],
            ]
        ));
    }
}
