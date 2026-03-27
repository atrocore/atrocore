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
    path: '/SelectionItem/action/replaceItem',
    methods: ['POST'],
    summary: 'Replaces a selection item entity',
    description: 'Replaces the entity of an existing selection item with another entity of the same scope.',
    tag: 'SelectionItem',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['id', 'selectedRecords'], 'properties' => ['id' => ['type' => 'string'], 'selectedRecords' => ['type' => 'array', 'items' => ['type' => 'string']]]]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class SelectionItemReplaceItemHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->id) || empty($data->selectedRecords)) {
            throw new BadRequest();
        }

        $result = $this->getRecordService('SelectionItem')->replaceItem($data->id, $data->selectedRecords[0]);

        return new BoolResponse(true);
    }
}
