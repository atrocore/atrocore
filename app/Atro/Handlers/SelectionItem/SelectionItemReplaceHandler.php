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
    path: '/SelectionItem/{id}/replace',
    methods: [
        'PATCH',
    ],
    summary: 'Replace the entity of a selection item',
    description: 'Swaps the entity record of an existing SelectionItem with another record of the same entity type.',
    tag: 'SelectionItem',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the SelectionItem record to update',
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
                        'selectedId',
                    ],
                    'properties' => [
                        'selectedId' => [
                            'type'        => 'string',
                            'description' => 'ID of the replacement entity record — must be the same entity type as the current item',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the SelectionItem was successfully updated',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to this item',
        ],
        404 => [
            'description' => 'Not found — no SelectionItem exists with the given ID',
        ],
    ],
)]
class SelectionItemReplaceHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id   = $request->getAttribute('id');
        $data = $this->getRequestBody($request);

        $res = $this->getRecordService('SelectionItem')->replaceItem($id, $data->selectedId);

        return new BoolResponse($res);
    }
}
