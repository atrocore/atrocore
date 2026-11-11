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

namespace Atro\Core\Middleware;

use Atro\Core\OAuth\Entities\ClientEntity;
use Atro\Core\OAuth\Repositories\ClientRepository;
use Atro\Core\UserContext;
use Atro\Entities\User;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Authenticates a request by the OAuth access token in the "Authorization: Bearer" header. The resource server
 * validates the token and puts its data into the request attributes (oauth_access_token_id, oauth_client_id,
 * oauth_user_id, oauth_scopes); the user of the token becomes the current user.
 */
class OauthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!preg_match('/^\s*Bearer\s/i', $request->getHeaderLine('Authorization'))) {
            return $handler->handle($request);
        }

        try {
            $request = $this->container->get(ResourceServer::class)->validateAuthenticatedRequest($request);
            $user = $this->getTokenUser($request);
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(new Response());
        }

        $user->loadLinkMultipleField('teams');
        $user->set('ipAddress', $_SERVER['REMOTE_ADDR'] ?? '');

        $this->container->get(UserContext::class)->set($user);
        $this->container->get('entityManager')->setUser($user);

        return $handler->handle($request);
    }

    private function getTokenUser(ServerRequestInterface $request): User
    {
        $userId = (string)$request->getAttribute('oauth_user_id');
        $clientId = (string)$request->getAttribute('oauth_client_id');

        // the subject of a token issued to the client itself (client credentials grant) is the client
        if ($userId === $clientId) {
            $client = $this->container->get(ClientRepository::class)->getClientEntity($clientId);
            $userId = $client instanceof ClientEntity ? (string)$client->getUserId() : '';
        }

        $user = $userId !== '' ? $this->container->get('entityManager')->getEntity('User', $userId) : null;
        if (!$user instanceof User || !$user->isActive()) {
            throw OAuthServerException::accessDenied('The user of the access token is not available');
        }

        return $user;
    }
}
