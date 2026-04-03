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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRestore',
    methods: [
        'POST',
    ],
    summary: 'Restore records',
    description: 'Restores one or multiple soft-deleted records from the recycle bin.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type' => 'string',
                        ],
                        'ids'        => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'where'      => [
                            'type' => 'array',
                        ],
                        'selectData' => [
                            'type' => 'object',
                        ],
                        'byWhere'    => [
                            'type' => 'boolean',
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
    ],
)]
class EntityRestoreHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'entityName') || empty($data->entityName)) {
            throw new BadRequest();
        }

        $entityName = (string) $data->entityName;

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $this->getRecordService($entityName)->massRestore($this->buildMassParams($data));

        return new BoolResponse(true);
    }
}
