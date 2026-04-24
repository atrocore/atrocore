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
    path: '/Selection/createWithItems',
    methods: [
        'POST',
    ],
    summary: 'Create a selection with multiple records',
    description: 'Creates a new Selection and adds the specified entity records to it in one request.',
    tag: 'Selection',
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
                            'description' => 'Entity type the selection is built for (e.g. "Product")',
                            'example'     => 'Product',
                        ],
                        'entityIds'  => [
                            'type'        => 'array',
                            'description' => 'IDs of the entity records to include in the selection',
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
            'description' => 'The created Selection record with its items pre-populated',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/Selection'
                    ]
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have create access to Selection',
        ],
    ],
    entities: ['Selection']
)]
class SelectionCreateWithItemsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $selection = $this->getRecordService('Selection')->createSelectionWithRecords($data->entityName, $data->entityIds);

        return new JsonResponse(DataUtil::toArray($selection->getValueMap()));
    }
}
