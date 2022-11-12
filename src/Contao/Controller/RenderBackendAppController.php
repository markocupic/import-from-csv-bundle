<?php

declare(strict_types=1);

/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

namespace Markocupic\ImportFromCsvBundle\Contao\Controller;

use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
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
    private ContaoFramework $framework;
    private ImportFromCsvHelper $importFromCsvHelper;
    private ContaoCsrfTokenManager $csrfTokenManager;
    private TwigEnvironment $twig;
    private RequestStack $requestStack;
    private string $csrfTokenName;

    public function __construct(ContaoFramework $framework, ImportFromCsvHelper $importFromCsvHelper, ContaoCsrfTokenManager $csrfTokenManager, TwigEnvironment $twig, RequestStack $requestStack, string $csrfTokenName)
    {
        $this->framework = $framework;
        $this->importFromCsvHelper = $importFromCsvHelper;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderAppAction(DataContainer $dc): Response
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $importFromCsvModelAdapter = $this->framework->getAdapter(ImportFromCsvModel::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_import_from_csv');

        $request = $this->requestStack->getCurrentRequest();
        $model = $importFromCsvModelAdapter->findByPk($dc->id);
        $arrData = $model->row();

        $csrfToken = $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue();

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
                    'action' => TL_SCRIPT,
                    'input' => [
                        'id' => $request->query->get('id'),
                        'csrfToken' => $csrfToken,
                    ],
                ],
            ]
        ));
    }
}
