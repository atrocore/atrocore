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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\OAuth\Authorization;
use Atro\Core\OAuth\Entities\ClientEntity;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/authorizationRequest',
    methods: [
        'GET',
    ],
    summary: 'Validate an OAuth authorization request',
    description: 'Returns the client that requests the access by the authorization request kept in its cookie by the authorization page /oauth/authorize. Called by the login form of the authorization request.',
    tag: 'Global',
    auth: false,
    responses: [
        200 => [
            'description' => 'The client that requests the access.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'clientName'        => [
                                'type' => 'string',
                            ],
                            'isConsentRequired' => [
                                'type'        => 'boolean',
                                'description' => 'Whether the user confirms that the client gets access to the account.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The authorization request is invalid.',
        ],
    ],
    skipActionHistory: true,
)]
class OauthAuthorizationRequestHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $this->container->get(Authorization::class);

        $query = $authorization->decryptRequest((string)($request->getCookieParams()[SessionCookie::AUTHORIZATION_REQUEST] ?? ''));
        if ($query === null) {
            throw new BadRequest($this->getLanguage()->translate('signInExpired', 'messages', 'OauthClient'));
        }

        try {
            $authorizationRequest = $authorization->validateQuery($query);
        } catch (OAuthServerException $e) {
            throw new BadRequest($e->getHint() ?: $e->getMessage());
        }

        $client = $authorizationRequest->getClient();

        return new JsonResponse([
            'clientName'        => $client->getName(),
            'isConsentRequired' => !$client instanceof ClientEntity || $client->isConsentRequired(),
        ]);
    }
}
