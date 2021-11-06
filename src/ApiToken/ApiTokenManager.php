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

namespace Markocupic\ImportFromCsvBundle\ApiToken;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\ImportFromCsvBundle\Controller\ImportController;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiTokenManager
{
    public const ALGORITHM_NAME = 'HS256';
    public const ALGORITHM = 'sha256';
    public const TOKEN_TYPE = 'JWT';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
    }

    public function createTokenFromBackendUser(BackendUser $user): string
    {
        $systemAdapter = $this->framework->getAdapter(System::class);
        $secret = $systemAdapter->getContainer()->getParameter('secret');

        $header = base64_encode(
            json_encode([
                'alg' => self::ALGORITHM_NAME,
                'typ' => self::TOKEN_TYPE,
            ])
        );

        $payload = base64_encode(
            json_encode([
                'allowed_api' => ImportController::API_NAME,
                'sub' => $user->id,
                'iat' => time(),
                'exp' => time() + 120 * 60,
            ])
        );

        $signature = hash_hmac(self::ALGORITHM, $header.'.'.$payload, $secret, false);

        return sprintf('%s.%s.%s', $header, $payload, $signature);
    }

    public function hasToken(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->query->has('apiToken');
    }

    public function getTokenFromRequest(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->query->get('apiToken', null);
    }

    /**
     * @param $strToken
     */
    public function getClaims(string $strToken): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === ($strToken = $request->query->get('apiToken', null))) {
            return null;
        }

        if (!$this->isValid($strToken)) {
            return false;
        }

        $chunks = explode('.', $strToken);

        return (array) json_decode(base64_decode($chunks[1], true));
    }

    /**
     * @param $strToken
     */
    public function isValid(string $strToken): bool
    {
        if (0 !== strpos($strToken, 'ey')) {
            return false;
        }
        $chunks = explode('.', $strToken);

        if (!\is_array($chunks) || 3 !== \count($chunks)) {
            return false;
        }

        $systemAdapter = $this->framework->getAdapter(System::class);
        $secret = $systemAdapter->getContainer()->getParameter('secret');

        $header = (array) json_decode(base64_decode($chunks[0], true));
        $payload = (array) json_decode(base64_decode($chunks[1], true));

        if (!\is_array($header) || !\is_array($payload)) {
            return false;
        }

        if ('JWT' !== $header['typ']) {
            return false;
        }

        $signature = hash_hmac(self::ALGORITHM, $chunks[0].'.'.$chunks[1], $secret, false);

        return $signature === $chunks[2];
    }
}
