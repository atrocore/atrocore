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
    path: '/settings',
    methods: [
        'GET',
    ],
    summary: 'Get system settings',
    description: 'Returns the values of the system configuration parameters.',
    tag: 'Global',
    responses: [
        200 => [
            'description' => 'Values of the Settings fields.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'    => 'object',
                        'example' => [
                            'recordsPerPage'  => 50,
                            'applicationName' => 'AtroPIM',
                            'siteUrl'         => 'https://pim.example.com',
                            'companyLogoId'   => null,
                            'companyLogoName' => null,
                        ],
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true,
)]
class SettingsReadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \Atro\Services\Settings $service */
        $service = $this->getServiceFactory()->create('Settings');

        return new JsonResponse($service->getConfigData());
    }
}
