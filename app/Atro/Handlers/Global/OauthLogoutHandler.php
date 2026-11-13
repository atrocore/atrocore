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

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\OAuth\BrowserSession;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/logout',
    methods: [
        'POST',
    ],
    summary: 'Sign out of the user interface',
    description: 'Ends the session of the user interface: revokes its OAuth tokens and clears the session cookies. Called by the user interface on logout.',
    tag: 'Global',
    auth: false,
    responses: [
        200 => [
            'description' => 'true when the session is ended',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true,
)]
class OauthLogoutHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $siteUrl = $this->getConfig()->getSiteUrl();

        $sessionId = SessionCookie::getSessionId($request);
        if ($sessionId !== null) {
            $this->container->get(BrowserSession::class)->logout($sessionId);
        }

        return (new BoolResponse(true))->withHeader('Set-Cookie', SessionCookie::buildClearHeaders($request, $siteUrl));
    }
}
