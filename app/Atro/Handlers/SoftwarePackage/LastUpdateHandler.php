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

namespace Atro\Handlers\SoftwarePackage;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/SoftwarePackage/lastUpdate',
    methods: [
        'GET',
    ],
    summary: 'Get the status of the last system update',
    description: 'Returns the status of the last system update ready to be displayed in the list view. Accessible by administrators only.',
    tag: 'SoftwarePackage',
    responses: [
        200 => [
            'description' => 'Status of the last system update.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'label' => [
                                'type'    => 'string',
                                'example' => 'Last Update',
                            ],
                            'value' => [
                                'type'        => 'string',
                                'nullable'    => true,
                                'description' => 'Null means there is nothing to display, the status is skipped.',
                                'example'     => 'Success',
                            ],
                            'style' => [
                                'type' => 'string',
                                'enum' => [
                                    'success',
                                    'danger',
                                    'warning',
                                    'info'
                                ],
                            ]
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Current user is not an administrator.',
        ],
    ],
)]
class LastUpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return new JsonResponse(($this->getRecordService('SoftwarePackage')->getLastUpdateStatus()));
    }
}
