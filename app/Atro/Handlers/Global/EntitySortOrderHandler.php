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
use Atro\Core\Services\AbstractRecordService;
use Atro\Core\Services\Interfaces\SortableInterface;

#[Route(
    path: '/entitySortOrder',
    methods: [
        'PATCH',
    ],
    summary: 'Sort records',
    description: 'Puts the records of the specified entity in the given order.

**How to use:**
- `entityName` — the entity name (e.g. `TranslationRule`). The entity has to define the `collection.sortField` parameter in `entityDefs.{entityName}`.
- `ids` — the IDs of the records in the new order.
- `offset` — the position of the first record of `ids` among all records, `0` by default. The other records keep their order around the sorted ones.',
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
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'    => 'string',
                            'example' => 'TranslationRule',
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
class EntitySortOrderHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data       = $this->getRequestBody($request);
        $entityName = (string)($data->entityName ?? '');

        if ($entityName === '' || !is_array($data->ids ?? null)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        /** @var AbstractRecordService $service */
        $service = $this->getRecordServiceWith($entityName, SortableInterface::class);

        return new BoolResponse($service->updateSortOrder($data->ids, (int)($data->offset ?? 0)));
    }
}
