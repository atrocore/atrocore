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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\DataUtil;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Selection/action/createSelectionWithRecords',
    methods: [
        'POST',
    ],
    summary: 'Creates a selection with multiple records',
    description: 'Creates a new selection and adds the specified entity records to it at once.',
    tag: 'Selection',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'scope'     => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'entityIds' => [
                            'type'  => 'array',
                            'items' => [
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
            'description' => 'Created Selection record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
    ],
)]
class SelectionCreateWithRecordsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->scope) || empty($data->entityIds)) {
            throw new BadRequest();
        }

        $selection = $this->getRecordService('Selection')->createSelectionWithRecords($data->scope, $data->entityIds);

        return new JsonResponse(DataUtil::toArray($selection->getValueMap()));
    }
}
