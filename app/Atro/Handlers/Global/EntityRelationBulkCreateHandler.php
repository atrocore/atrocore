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
    path: '/entityRelationBulk',
    methods: [
        'POST',
    ],
    summary: 'Add relations in bulk',
    description: 'Creates relations between multiple records. Accepts either explicit IDs (ids + foreignIds) or filter conditions (where + foreignWhere).',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'link',
                    ],
                    'properties' => [
                        'entityName'   => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product")',
                        ],
                        'link'         => [
                            'type'        => 'string',
                            'description' => 'Relation link name (e.g. "categories")',
                        ],
                        'ids'          => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'string',
                            ],
                            'description' => 'IDs of main entity records. Required when where/foreignWhere are not provided.',
                        ],
                        'foreignIds'   => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'string',
                            ],
                            'description' => 'IDs of foreign entity records to link. Required together with ids.',
                        ],
                        'where'        => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'object',
                            ],
                            'description' => 'Filter conditions for main entity records. Required when ids/foreignIds are not provided.',
                        ],
                        'foreignWhere' => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'object',
                            ],
                            'description' => 'Filter conditions for foreign entity records. Required together with where.',
                        ],
                        'data'         => [
                            'type'        => 'object',
                            'description' => 'Extra relation attributes (e.g. {"associationId": "..."})',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Operation result',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'required'   => [
                            'message',
                        ],
                        'properties' => [
                            'message' => [
                                'type'        => 'string',
                                'description' => 'Human-readable result message displayed to the user',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class EntityRelationBulkCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data       = $this->getRequestBody($request);
        $entityName = (string) ($data->entityName ?? '');
        $link       = (string) ($data->link ?? '');

        if (empty($entityName) || empty($link)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $relationData = json_decode(json_encode($data->data ?? new \stdClass()), true);
        $service      = $this->getServiceFactory()->create('MassActions');

        if (property_exists($data, 'where') && property_exists($data, 'foreignWhere')) {
            $where        = json_decode(json_encode($data->where), true);
            $foreignWhere = json_decode(json_encode($data->foreignWhere), true);

            return new JsonResponse($service->addRelationByWhere($where, $foreignWhere, $entityName, $link, $relationData));
        }

        if (property_exists($data, 'ids') && property_exists($data, 'foreignIds')) {
            if (!is_array($data->ids) || !is_array($data->foreignIds)) {
                throw new BadRequest();
            }

            return new JsonResponse($service->addRelation($data->ids, $data->foreignIds, $entityName, $link, $relationData));
        }

        throw new BadRequest();
    }
}
