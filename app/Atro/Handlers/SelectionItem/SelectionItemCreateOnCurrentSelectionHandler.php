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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/SelectionItem/action/createOnCurrentSelection',
    methods: [
        'POST',
    ],
    summary: 'Creates a selection item on current selection',
    description: 'Creates a selection item for the current active selection of the user.',
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
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'entityId'   => [
                            'type'    => 'string',
                            'example' => 'example-id',
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
class SelectionItemCreateOnCurrentSelectionHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        $result = $this->getRecordService('SelectionItem')->createOnCurrentItem($data->entityName, $data->entityId);

        return new BoolResponse(true);
    }
}
