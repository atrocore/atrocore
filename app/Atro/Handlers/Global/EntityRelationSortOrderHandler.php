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
use Atro\Services\Record;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRelationSortOrder',
    methods: [
        'PATCH',
    ],
    summary: 'Sort linked records',
    description: 'Puts the records linked to the specified entity record via the given relation in the given order.

**How to use:**
- `entityName` — the entity name (e.g. `Category`).
- `id` — the ID of the record.
- `link` — the relation name as defined in `entityDefs.{entityName}.links` (e.g. `products`). The relation has to define the `sortField` parameter.
- `ids` — the IDs of the linked records in the new order.
- `offset` — the position of the first record of `ids` among all linked records, `0` by default. The other linked records keep their order around the sorted ones.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'id',
                        'link',
                        'ids',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'    => 'string',
                            'example' => 'Category',
                        ],
                        'id'         => [
                            'type' => 'string',
                        ],
                        'link'       => [
                            'type'    => 'string',
                            'example' => 'products',
                        ],
                        'ids'        => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'offset'     => [
                            'type'    => 'integer',
                            'minimum' => 0,
                            'default' => 0,
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
class EntityRelationSortOrderHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data       = $this->getRequestBody($request);
        $entityName = (string)($data->entityName ?? '');
        $id         = (string)($data->id ?? '');
        $link       = (string)($data->link ?? '');

        if ($entityName === '' || $id === '' || $link === '' || !is_array($data->ids ?? null)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        /** @var Record $service */
        $service = $this->getRecordService($entityName);

        return new BoolResponse($service->updateRelationSortOrder($id, $link, $data->ids, (int)($data->offset ?? 0)));
    }
}
