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

namespace Atro\Handlers\ClassificationAttribute;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClassificationAttribute/{id}',
    methods: ['DELETE'],
    summary: 'Deletes a ClassificationAttribute record',
    description: 'Deletes a ClassificationAttribute record by ID. Pass withAttributeValues=true in the request body to also delete related attribute values.',
    tag: 'ClassificationAttribute',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class ClassificationAttributeDeleteHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id      = (string) $request->getAttribute('id');
        $data    = $this->getRequestBody($request);
        $service = $this->getRecordService('ClassificationAttribute');

        if (!empty($data->withAttributeValues)) {
            $service->deleteEntityWithThemAttributeValues($id);
        } else {
            $service->deleteEntity($id);
        }

        return new JsonResponse(['true' => true]);
    }
}
