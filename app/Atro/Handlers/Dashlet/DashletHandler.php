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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\InternalServerError;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Atro\Services\DashletInterface;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Dashlet/{dashletName}',
    methods: ['GET'],
    summary: 'Get dashlet data',
    description: 'Returns rendered data for the specified dashlet widget.',
    tag: 'Dashlet',
    parameters: [
        [
            'name'        => 'dashletName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Dashlet service name (e.g. Activities)',
            'schema'      => [
                'type' => 'string'
            ]
        ],
    ],
    responses: [
        200 => [
            'description' => 'Dashlet data',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object'
                    ]
                ]
            ]
        ],
        400 => ['description' => 'dashletName is required'],
    ],
)]
class DashletHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $dashletName = $routeResult ? ($routeResult->getMatchedParams()['dashletName'] ?? null) : null;

        if (empty($dashletName)) {
            throw new BadRequest('dashletName is required');
        }

        $serviceName = ucfirst($dashletName) . 'Dashlet';
        $dashletService = $this->getServiceFactory()->create($serviceName);

        if (!$dashletService instanceof DashletInterface) {
            throw new InternalServerError(sprintf($this->getLanguage()->translate('notDashletService'), $dashletService));
        }

        return new JsonResponse($dashletService->getDashlet());
    }
}