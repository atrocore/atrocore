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
    summary: 'Mass update (synchronous)',
    description: 'Updates multiple records of the specified entity synchronously and returns the result immediately.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'ids',
                        'values',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'ids'        => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                                'example' => 'some-id',
                            ],
                        ],
                        'values'     => [
                            'type'        => 'object',
                            'description' => 'Field values to apply to every matched record. The accepted keys depend on the entity specified in `entityName` — use the same field names as in a regular entity update request.',
                            'example'     => ['status' => 'Active', 'assignedUserId' => 'user-uuid'],
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
                            'updated' => [
                                'type' => 'integer',
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
            'description' => 'entityName, attributes or ids are missing, or ids count exceeds the configured limit',
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

        if (empty($data->values)) {
            throw new BadRequest('values is required');
        }

        $entityName = (string)$data->entityName;

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $ids = $data->ids ?? [];
        $limit = $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
        if (count($ids) > $limit) {
            throw new BadRequest("Too many ids: maximum allowed is $limit. Use /entityMassUpdateAsync for large batches.");
        }

        $params = ['ids' => $ids, 'maxCountWithoutJob' => PHP_INT_MAX];
        $result = $this->getRecordService($entityName)->massUpdate($data->values, $params);

        return new JsonResponse(['updated' => $result['count'], 'errors' => $result['errors']]);
    }
}
