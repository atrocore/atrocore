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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Atro\Services\DashletInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Dashlet/{dashletName}',
    methods: [
        'GET',
    ],
    summary: 'Get dashlet data',
    description: 'Returns data for the specified dashlet by calling its `getDashlet()` method.',
    tag: 'Dashlet',
    parameters: [
        [
            'name'        => 'dashletName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Dashlet name (e.g. `ProductsByStatus`, `Efficiency`). The handler resolves `{DashletName}Dashlet` service.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'ProductsByStatus',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Dashlet data. Structure depends on the specific dashlet service.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'dashletName is missing',
        ],
        404 => [
            'description' => 'No such dashlet found',
        ],
    ],
)]
class DashletGetHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $dashletName = (string)$request->getAttribute('dashletName', '');

        if ($dashletName === '') {
            throw new BadRequest('dashletName is required.');
        }

        $serviceName = ucfirst($dashletName) . 'Dashlet';
        $service = $this->getServiceFactory()->create($serviceName);

        if ($service instanceof DashletInterface) {
            return new JsonResponse($service->getDashlet());
        }

        throw new NotFound('No such dashlet found');
    }
}