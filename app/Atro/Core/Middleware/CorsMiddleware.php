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

use Atro\Core\OAuth\Repositories\ClientRepository;
use GuzzleHttp\Psr7\Response;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Cross-origin resource sharing for the browser-based OAuth clients: the origins of their redirect URIs may call the
 * API with an access token. The requests carry no cookies, so credentials are not allowed.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private const ALLOWED_METHODS = 'GET, POST, PUT, PATCH, DELETE';
    private const ALLOWED_HEADERS = 'Authorization, Content-Type';
    private const EXPOSED_HEADERS = 'X-Status-Reason';
    private const MAX_AGE = '600';

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $isAllowedOrigin = $origin !== '' && !$this->isSameOrigin($origin, $request) && $this->isAllowedOrigin($origin);

        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(200);
            if (!$isAllowedOrigin) {
                return $response;
            }

            return $this->withCorsHeaders($response, $origin)
                ->withHeader('Access-Control-Allow-Methods', self::ALLOWED_METHODS)
                ->withHeader('Access-Control-Allow-Headers', self::ALLOWED_HEADERS)
                ->withHeader('Access-Control-Max-Age', self::MAX_AGE);
        }

        $response = $handler->handle($request);

        return $isAllowedOrigin ? $this->withCorsHeaders($response, $origin) : $response;
    }

    private function isSameOrigin(string $origin, ServerRequestInterface $request): bool
    {
        $parts = parse_url($origin);
        $authority = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return strcasecmp($authority, $request->getHeaderLine('Host')) === 0;
    }

    private function isAllowedOrigin(string $origin): bool
    {
        return in_array(strtolower($origin), $this->container->get(ClientRepository::class)->getPublicClientOrigins(), true);
    }

    private function withCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Expose-Headers', self::EXPOSED_HEADERS)
            ->withAddedHeader('Vary', 'Origin');
    }
}
