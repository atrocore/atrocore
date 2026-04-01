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
    path: '/navigationHistory',
    methods: [
        'GET',
    ],
    summary: 'Get navigation history',
    description: 'Returns recently viewed records for the current user in the context of a given entity type. '
        . 'Used exclusively by the breadcrumb navigation component in the UI header to render '
        . 'previous/next record arrows. '
        . "\n\n"
        . 'Results are deduplicated per `(entityType, recordId)` pair using a window function '
        . '(`ROW_NUMBER() OVER (PARTITION BY controller_name, target_id)`), '
        . 'so each record appears at most once regardless of how many times it was visited. '
        . "\n\n"
        . '`tabId` isolates history per browser tab — each tab maintains its own navigation context. '
        . 'When `id` is provided, the current record is excluded from the result '
        . 'so that the breadcrumb only shows other records the user navigated through.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entity',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Entity type to scope the history to (e.g. `Product`, `Category`). '
                . 'When omitted, history across all entity types is returned.',
            'schema'      => ['type' => 'string'],
            'example'     => 'Product',
        ],
        [
            'name'        => 'id',
            'in'          => 'query',
            'required'    => false,
            'description' => 'ID of the currently open record. Used to exclude it from the result set.',
            'schema'      => ['type' => 'string'],
        ],
        [
            'name'        => 'tabId',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Browser tab identifier (from `sessionStorage.tabId`). '
                . 'Scopes the history to the current browser tab so that navigation in one tab '
                . 'does not affect the breadcrumbs in another.',
            'schema'      => ['type' => 'string'],
        ],
        [
            'name'        => 'maxSize',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Maximum number of history records to return. Defaults to `3`.',
            'schema'      => ['type' => 'integer'],
            'example'     => 32,
        ],
    ],
    responses: [
        200 => [
            'description' => 'Recently viewed records for the given entity type.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total'      => [
                                'type'        => 'integer',
                                'description' => 'Total number of matching history records (before `maxSize` is applied).',
                                'example'     => 10,
                            ],
                            'collection' => [
                                'type'        => 'array',
                                'description' => 'Deduplicated list of recently viewed records, newest first.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'controllerName' => [
                                            'type'        => 'string',
                                            'description' => 'Entity type of the viewed record (e.g. `Product`).',
                                        ],
                                        'targetId'       => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'ID of the viewed record. `null` for non-record pages (e.g. list views).',
                                        ],
                                        'targetName'     => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'Display name of the viewed record at the time of the visit.',
                                        ],
                                        'targetUrl'      => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'Hash URL to navigate back to this record (e.g. `#Product/view/abc123`).',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    hidden: true,
    skipActionHistory: true,
)]
class NavigationHistoryHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();

        $entityName = $query['entity'] ?? null;
        $entityId   = $query['id'] ?? null;
        $tabId      = $query['tabId'] ?? null;
        $maxSize    = (int)($query['maxSize'] ?? 0) ?: 3;

        /** @var \Atro\Services\LastViewed $service */
        $service = $this->getServiceFactory()->create('LastViewed');

        return new JsonResponse(
            $service->getLastEntities($maxSize, $entityName, $entityId, $tabId)
        );
    }
}
