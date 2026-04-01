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

namespace Atro\Handlers\LastViewed;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LastViewed',
    methods: [
        'GET',
    ],
    summary: 'Get last viewed records',
    description: 'Returns a paginated list of recently viewed records for the current user. '
        . 'Results are deduplicated per `(entityType, recordId)` pair and ordered by the most recent visit. '
        . 'Only entity types with `object: true` and without `hideLastViewed: true` in their scope metadata are included. '
        . 'Records that have been deleted are automatically excluded (`skipDeleted`). '
        . 'The `id` field in each list item is a synthetic sequential integer (`offset + index`) '
        . 'used for UI rendering — the actual record ID is `targetId`.',
    tag: 'LastViewed',
    parameters: [
        [
            'name'        => 'offset',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Number of records to skip. Used for pagination.',
            'schema'      => ['type' => 'integer'],
            'example'     => 0,
        ],
        [
            'name'        => 'maxSize',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Maximum number of records to return. Defaults to `lastViewedCount` from system config (typically 20).',
            'schema'      => ['type' => 'integer'],
            'example'     => 20,
        ],
    ],
    responses: [
        200 => [
            'description' => 'Deduplicated list of recently viewed records, newest first.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'    => 'object',
                        'example' => [
                            'total' => 42,
                            'list'  => [
                                [
                                    'id'             => 0,
                                    'controllerName' => 'Product',
                                    'targetId'       => 'abc123',
                                    'targetName'     => 'My Product',
                                ],
                                [
                                    'id'             => 1,
                                    'controllerName' => 'Category',
                                    'targetId'       => 'def456',
                                    'targetName'     => 'Electronics',
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
class ListHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();

        $offset  = (int)($query['offset'] ?? 0);
        $maxSize = (int)($query['maxSize'] ?? 0);

        $params = [
            'offset'      => $offset,
            'maxSize'     => $maxSize,
            'skipDeleted' => true,
        ];

        /** @var \Atro\Services\LastViewed $service */
        $service = $this->getServiceFactory()->create('LastViewed');
        $result  = $service->get($params);

        return new JsonResponse([
            'total' => $result['total'],
            'list'  => isset($result['collection']) ? $result['collection']->toArray() : $result['list'],
        ]);
    }
}
