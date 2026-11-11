<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\OAuth;

use Atro\Core\Container;
use Atro\Core\OAuth\Repositories\ClientRepository;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The sign-in of the user interface in the browser: /oauth/login starts the authorization request of the OAuth client
 * of the user interface, /oauth/callback receives the authorization code and opens the session.
 */
class BrowserLogin
{
    public function __construct(
        private readonly Container $container,
        private readonly BrowserSession $browserSession,
        private readonly UiClient $uiClient
    ) {
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $uiClientId = $this->uiClient->getId();
        if ($uiClientId === null || $this->container->get(ClientRepository::class)->getClientEntity($uiClientId) === null) {
            return $this->messageResponse(403, 'uiClientDisabled');
        }

        // only a route of the user interface may be returned to
        $returnHash = (string)($request->getQueryParams()['return'] ?? '');
        if (!str_starts_with($returnHash, '#')) {
            $returnHash = '';
        }

        $login = $this->browserSession->startLogin($returnHash);

        return (new Response(302))
            ->withHeader('Location', $login['authorizationUrl'])
            ->withHeader('Set-Cookie', SessionCookie::buildLoginHeader($request, $this->getSiteUrl(), $login['loginCookie']));
    }

    public function callback(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $loginCookie = (string)($request->getCookieParams()[SessionCookie::LOGIN] ?? '');

        if (empty($params['code']) || empty($params['state']) || $loginCookie === '') {
            return $this->messageResponse(400, 'signInFailed');
        }

        try {
            $result = $this->browserSession->completeLogin(
                (string)$params['code'],
                (string)$params['state'],
                $loginCookie,
                (string)($request->getServerParams()['REMOTE_ADDR'] ?? '')
            );
        } catch (OAuthServerException $e) {
            $result = null;
        }

        if ($result === null) {
            return $this->messageResponse(400, 'signInFailed');
        }

        return (new Response(302))
            ->withHeader('Location', $this->getSiteUrl() . '/' . $result['returnHash'])
            ->withHeader('Set-Cookie', SessionCookie::buildSessionHeaders($request, $this->getSiteUrl(), $result['sessionId']));
    }

    private function messageResponse(int $status, string $message): ResponseInterface
    {
        $text = htmlspecialchars($this->container->get('language')->translate($message, 'messages', 'OauthClient'));
        $link = htmlspecialchars($this->getSiteUrl() . '/oauth/login');

        $html = "<!doctype html><html><head><meta charset=\"utf-8\"><title>AtroCore</title></head>"
            . "<body style=\"font-family: sans-serif; text-align: center; margin-top: 15vh\"><p>$text</p>"
            . ($status === 400 ? "<p><a href=\"$link\">$link</a></p>" : '')
            . "</body></html>";

        return new Response($status, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function getSiteUrl(): string
    {
        return rtrim($this->container->get('config')->getSiteUrl(), '/');
    }
}
