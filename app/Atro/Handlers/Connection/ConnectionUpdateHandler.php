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

namespace Atro\Handlers\Connection;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Connection/{id}',
    methods: [
        'PATCH',
    ],
    summary: 'Updates a connection record',
    description: 'Updates an existing connection record by ID. Accessible by administrators only.',
    tag: 'Connection',
    parameters: [
        [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Updated connection record',
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
class ConnectionUpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $id      = (string) $request->getAttribute('id');
        $data    = $this->getRequestBody($request);
        $service = $this->getRecordService('Connection');

        $service->updateEntity($id, $data);

        $entity = $service->prepareEntityById($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        return new JsonResponse((array) $entity->getValueMap());
    }
}
