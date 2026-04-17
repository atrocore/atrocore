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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRelation',
    methods: [
        'GET',
    ],
    summary: 'List linked records',
    description: 'Returns a paginated list of records linked to the specified entity record via the given relation.

**How to use:**
- `entityName` — the entity name (e.g. `Product`, `Category`).
- `id` — the ID of the record.
- `link` — the relation name as defined in `entityDefs.{entityName}.links` (e.g. `channels`, `assets`, `categories`). Valid link names can be discovered from the entity metadata.

**Note:** The structure of items in the `list` array depends on the linked entity type resolved from the relation definition. There is no single fixed response schema — it varies per `entityName` + `link` combination.',
    tag: 'Global',
    parameters: [
        [
            'name'     => 'entityName',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type'    => 'string',
                'example' => 'Product',
            ],
        ],
        [
            'name'     => 'id',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'link',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type'    => 'string',
                'example' => 'channels',
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
                'example' => 50,
            ],
        ],
        [
            'name'     => 'sortBy',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'string',
                'example' => 'name',
            ],
        ],
        [
            'name'     => 'asc',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'anyOf'   => [
                    [
                        'type' => 'boolean',
                    ],
                    [
                        'type' => 'string',
                    ],
                ],
                'example' => true,
            ],
        ],
        [
            'name'     => 'where',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'anyOf' => [
                    [
                        'type' => 'array',
                    ],
                    [
                        'type' => 'object',
                    ],
                    [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Paginated list of linked records. Item schema varies depending on the linked entity type resolved from the relation definition.',
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
class EntityRelationListHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp         = $request->getQueryParams();
        $entityName = (string) ($qp['entityName'] ?? '');
        $id         = (string) ($qp['id'] ?? '');
        $link       = (string) ($qp['link'] ?? '');

        if ($entityName === '' || $id === '' || $link === '') {
            throw new NotFound();
        }

        $params                  = $this->buildListParams($request);
        $params['whereRelation'] = $this->prepareWhereQuery($qp['whereRelation'] ?? null);

        $result = $this->getRecordService($entityName)->findLinkedEntities($id, $link, $params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}
