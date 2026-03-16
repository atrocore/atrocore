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

namespace Atro\Handlers\Dashlet;

use Atro\Core\Container;
use Atro\Core\Exceptions;
use Atro\Core\Routing\Route;
use Atro\Services\DashletInterface;
use GuzzleHttp\Psr7\Response;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route('/Dashlet/{dashletName}', methods: ['GET'])]
class DashletHandler implements MiddlewareInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $dashletName = $routeResult ? ($routeResult->getMatchedParams()['dashletName'] ?? null) : null;

        if (empty($dashletName)) {
            return $this->errorResponse(400, 'dashletName is required');
        }

        try {
            $result = $this->createDashletService($dashletName)->getDashlet();

            return $this->jsonResponse(json_encode($result));
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getCode() ?: 500, $e->getMessage());
        }
    }

    private function createDashletService(string $dashletName): DashletInterface
    {
        $serviceName    = ucfirst($dashletName) . 'Dashlet';
        $dashletService = $this->container->get('serviceFactory')->create($serviceName);

        if (!$dashletService instanceof DashletInterface) {
            $language = $this->container->get('language');
            throw new Exceptions\Error(sprintf($language->translate('notDashletService'), $serviceName));
        }

        return $dashletService;
    }

    private function jsonResponse(string $body): ResponseInterface
    {
        return new Response(
            200,
            [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Expires'       => '0',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ],
            $body
        );
    }

    private function errorResponse(int $code, string $message): ResponseInterface
    {
        return new Response(
            $code,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['message' => $message])
        );
    }
}
