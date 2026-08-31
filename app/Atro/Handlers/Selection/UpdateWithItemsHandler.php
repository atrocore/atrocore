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

namespace Atro\Handlers\Selection;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\DataUtil;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Selection/{id}/updateWithItems',
    methods: [
        'POST',
    ],
    summary: 'Add multiple records to an existing selection',
    description: 'Adds the specified entity records as items to the given existing Selection.',
    tag: 'Selection',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Selection record ID',
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
                    'required'   => [
                        'entityName',
                        'entityIds',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity type the records belong to (e.g. "Product")',
                            'example'     => 'Product',
                        ],
                        'entityIds'  => [
                            'type'        => 'array',
                            'description' => 'IDs of the entity records to add to the selection',
                            'items'       => [
                                'type'    => 'string',
                                'example' => 'example-id',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The updated Selection record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/Selection'
                    ]
                ],
            ],
        ],
        400 => [
            'description' => 'The Selection is of type "single" and entityName does not match its entity type',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to the Selection',
        ],
        404 => [
            'description' => 'Selection not found',
        ],
    ],
    entities: ['Selection']
)]
class UpdateWithItemsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $selection = $this->getRecordService('Selection')->addRecordsToSelection(
            $request->getAttribute('id'),
            $data->entityName,
            $data->entityIds
        );

        return new JsonResponse(DataUtil::toArray($selection->getValueMap()));
    }
}