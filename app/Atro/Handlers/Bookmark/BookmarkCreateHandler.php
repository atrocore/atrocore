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

namespace Atro\Handlers\Bookmark;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Bookmark',
    methods: [
        'POST',
    ],
    summary: 'Creates a bookmark',
    description: 'Creates a new bookmark record.',
    tag: 'Bookmark',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityId',
                        'entityType',
                    ],
                    'properties' => [
                        'entityId'   => [
                            'type' => 'string',
                        ],
                        'entityType' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Created bookmark record',
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
class BookmarkCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityId) || empty($data->entityType)) {
            throw new BadRequest();
        }

        $service = $this->getRecordService('Bookmark');

        $id = $service->createEntity($data);

        $entity = $service->prepareEntityById($id);
        if (empty($entity)) {
            throw new Error();
        }

        return new JsonResponse((array) $entity->getValueMap());
    }
}
