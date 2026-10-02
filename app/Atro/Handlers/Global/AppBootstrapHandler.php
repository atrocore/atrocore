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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/appBootstrap',
    methods: [
        'GET',
    ],
    summary: 'Get application bootstrap data',
    description: 'Returns everything the frontend needs to start.',
    tag: 'Global',
    auth: false,
    responses: [
        200 => [
            'description' => 'Application bootstrap data.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'    => 'object',
                        'example' => [
                            'language'    => 'en_US',
                            'dateFormat'  => 'MM/DD/YYYY',
                            'timeFormat'  => 'HH:mm',
                            'timeZone'    => 'UTC',
                            'weekStart'   => 0,
                            'coreVersion' => '1.14.0',
                            'jsLibs'      => [
                                'jsTree' => [
                                    'path'      => 'client/lib/jstree.min.js',
                                    'exportsTo' => 'jQuery',
                                ],
                            ],
                            'themes'      => [
                                'AtroCore' => [
                                    'stylesheet' => 'client/css/atrocore.css',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true,
)]
class AppBootstrapHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse($this->getServiceFactory()->create('App')->getBootstrapData());
    }
}
