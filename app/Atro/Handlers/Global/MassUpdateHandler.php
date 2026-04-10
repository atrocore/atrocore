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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityMassUpdate',
    methods: [
        'PATCH',
    ],
    summary: 'Mass update',
    description: 'Updates multiple records of the specified entity.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'attributes',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type' => 'string',
                        ],
                        'attributes' => [
                            'type' => 'object',
                        ],
                        'ids'        => [
                            'type'     => 'array',
                            'items'    => [
                                'type' => 'string',
                            ],
                            'nullable' => true,
                        ],
                        'where'      => [
                            'type'     => 'array',
                            'nullable' => true,
                        ],
                        'selectData' => [
                            'type'     => 'object',
                            'nullable' => true,
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
                        'type'       => 'object',
                        'properties' => [
                            'count'  => [
                                'type'        => 'integer',
                                'description' => 'Number of successfully updated records.',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'Whether the update was performed synchronously (true) or dispatched as a background job (false).',
                            ],
                            'errors' => [
                                'type'        => 'array',
                                'description' => 'List of error messages for records that could not be updated.',
                                'items'       => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or attributes are missing',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class MassUpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName)) {
            throw new BadRequest('entityName is required');
        }

        if (empty($data->attributes)) {
            throw new BadRequest('attributes is required');
        }

        $entityName = (string) $data->entityName;

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $params = $this->buildMassParams($data);
        $result = $this->getRecordService($entityName)->massUpdate($data->attributes, $params);

        return new JsonResponse($result);
    }
}
