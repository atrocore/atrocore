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

namespace Atro\Handlers\SelectionItem;

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/SelectionItem/createOnCurrentSelection',
    methods: [
        'POST',
    ],
    summary: 'Add a record to the current selection',
    description: 'Creates a SelectionItem that links the given entity record to the current active Selection of the user.',
    tag: 'SelectionItem',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'entityId',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity type of the record to add (e.g. "Product")',
                            'example'     => 'Product',
                        ],
                        'entityId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the record to add to the current selection',
                            'example'     => 'example-id',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the item was successfully added to the current selection',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have access to the current selection',
        ],
    ],
)]
class SelectionItemCreateOnCurrentSelectionHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $res = $this->getRecordService('SelectionItem')->createOnCurrentItem($data->entityName, $data->entityId);

        return new BoolResponse($res);
    }
}
