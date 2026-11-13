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

namespace Atro\Handlers\Global;

use Atro\Core\OAuth\BrowserSession;
use Atro\Core\OAuth\ExternalSignIn;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/callback',
    methods: [
        'GET',
    ],
    summary: 'OAuth callback of the user interface',
    description: 'The redirect URI of the OAuth client of the user interface: receives the authorization code of a sign-in at an external identity provider, opens the session of the user interface and returns to the route the sign-in started from.',
    tag: 'Global',
    auth: false,
    parameters: [
        [
            'name'     => 'code',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'state',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        302 => [
            'description' => 'To the user interface.',
        ],
    ],
    hidden: true,
    skipActionHistory: true,
)]
class OauthCallbackHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getQueryParams();
        $loginCookie = (string)($request->getCookieParams()[SessionCookie::LOGIN] ?? '');

        $result = null;
        if (!empty($params['code']) && !empty($params['state']) && $loginCookie !== '') {
            try {
                $result = $this->container->get(BrowserSession::class)->completeLogin(
                    (string)$params['code'],
                    (string)$params['state'],
                    $loginCookie,
                    (string)($request->getServerParams()['REMOTE_ADDR'] ?? '')
                );
            } catch (OAuthServerException $e) {
                $result = null;
            }
        }

        if ($result === null) {
            return $this->container->get(ExternalSignIn::class)
                ->fail($request, $this->getLanguage()->translate('signInFailed', 'messages', 'OauthClient'));
        }

        $siteUrl = $this->getConfig()->getSiteUrl();

        return (new Response(302))
            ->withHeader('Location', rtrim($siteUrl, '/') . '/' . $result['returnHash'])
            ->withHeader('Set-Cookie', SessionCookie::buildSessionHeaders($request, $siteUrl, $result['sessionId']));
    }
}
