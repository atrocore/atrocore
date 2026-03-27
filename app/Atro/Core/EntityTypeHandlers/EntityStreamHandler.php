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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/{id}/stream',
    methods: [
        'GET',
    ],
    summary: 'Returns the stream for a record',
    description: 'Returns stream entries for the specified entity record.',
    tag: '{entityName}',
    parameters: [
        [
            'name'     => 'entityName',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'offset',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
        [
            'name'     => 'maxSize',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 20,
            ],
        ],
        [
            'name'     => 'after',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'filter',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
        ],
        [
            'name'     => 'sortBy',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'string',
                'example' => 'number',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Collection of stream entries',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type' => 'integer',
                            ],
                            'list'  => [
                                'type'  => 'array',
                                'items' => [
                                    'type' => 'object',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], requiresAbsent: ['streamDisabled'])]
class EntityStreamHandler extends AbstractHandler
{
    private const MAX_SIZE_LIMIT = 200;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');
        $qp         = $request->getQueryParams();
        $maxSize    = (int) ($qp['maxSize'] ?? 0);

        if (empty($maxSize)) {
            $maxSize = self::MAX_SIZE_LIMIT;
        }
        if ($maxSize > self::MAX_SIZE_LIMIT) {
            throw new Forbidden();
        }

        $result = $this->getServiceFactory()->create('Stream')->find($entityName, $id, [
            'offset'  => (int) ($qp['offset'] ?? 0),
            'maxSize' => $maxSize,
            'after'   => $qp['after'] ?? null,
            'filter'  => $qp['filter'] ?? null,
            'orderBy' => $qp['sortBy'] ?? 'number',
        ]);

        return new JsonResponse([
            'total' => $result['total'],
            'list'  => $result['collection']->toArray(),
        ]);
    }
}
