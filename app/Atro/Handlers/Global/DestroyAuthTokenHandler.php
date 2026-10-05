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

use Atro\Core\Http\AuthCookie;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Espo\Core\Utils\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/destroyAuthToken',
    methods: [
        'POST',
    ],
    summary: 'Invalidate the current auth token',
    description: 'Invalidates the auth token the request is authenticated with, effectively logging out the current session. For requests authenticated by the auth cookie, the auth cookie is cleared. Called by the UI on logout.',
    tag: 'Global',
    responses: [
        200 => [
            'description' => 'true if the token was invalidated',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
    ],
)]
class DestroyAuthTokenHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new BoolResponse((new Auth($this->container))->destroyAuthToken((string)$this->getUser()->get('token')));

        if ($request->getAttribute('isCookieAuth')) {
            $response = $response->withHeader('Set-Cookie', AuthCookie::buildClearHeaders($request, $this->getConfig()->getSiteUrl()));
        }

        return $response;
    }
}
