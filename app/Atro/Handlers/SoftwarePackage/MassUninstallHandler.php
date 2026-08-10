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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/SoftwarePackage/uninstall',
    methods: [
        'DELETE',
    ],
    summary: 'Uninstall software packages',
    description: 'Queues the selected installed software packages for uninstallation. Accessible by administrators only.',
    tag: 'SoftwarePackage',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'ids',
                    ],
                    'properties' => [
                        'ids' => [
                            'type'        => 'array',
                            'description' => 'IDs of the software packages to uninstall.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'ids is required.',
        ],
        403 => [
            'description' => 'Current user is not an administrator.',
        ],
    ],
)]
class MassUninstallHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        return new BoolResponse($this->getRecordService('SoftwarePackage')->uninstall($data->ids));
    }
}