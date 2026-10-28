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

namespace Atro\Core\EntityTypeHandlers;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/massErasePersonalData',
    methods: [
        'POST',
    ],
    summary: 'Mass erase personal data',
    description: 'Erases the personal data fields of multiple records according to the erasure mode of each field. Small batches are processed immediately, larger ones are delegated to a job. Available for global administrators and data privacy administrators only.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name that contains personal data (e.g. "Person")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'ids'   => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'IDs of the records. Ignored when `where` is provided.',
                        ],
                        'where' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'Where clause to select the records.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Result of the erasure: `count` and `errors` when processed immediately, or `jobId` when delegated to a job',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'sync'   => ['type' => 'boolean'],
                            'count'  => ['type' => 'integer'],
                            'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'jobId'  => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user is neither a global administrator nor a data privacy administrator',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['containsPersonalData'])]
class MassErasePersonalDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        $params = property_exists($data, 'where')
            ? ['where' => json_decode(json_encode($data->where), true)]
            : ['ids' => $data->ids ?? []];

        $result = $this->getRecordService($entityName)->massErasePersonalData($params);

        return new JsonResponse($result);
    }
}
