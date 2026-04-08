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

namespace Atro\Handlers\File;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/{id}/reupload',
    methods: [
        'PATCH',
    ],
    summary: 'Reupload file content',
    description: 'Replaces the content of an existing File record. Supply either `fileContents` (base64 data URI) or a remote `url`.',
    tag: 'File',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the File record whose content should be replaced.',
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
                    'allOf' => [
                        [
                            '$ref' => '#/components/schemas/File',
                        ],
                        [
                            'type'       => 'object',
                            'properties' => [
                                'fileContents' => [
                                    'type'        => 'string',
                                    'description' => 'Base64-encoded file content as a data URI (e.g. `data:image/png;base64,...`).',
                                ],
                                'url'          => [
                                    'type'        => 'string',
                                    'description' => 'Remote URL to fetch the new content from.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The updated File record.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/File',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Missing or invalid fields.',
        ],
        403 => [
            'description' => 'The current user does not have File edit permission.',
        ],
        404 => [
            'description' => 'File record not found.',
        ],
    ],
    entities: [
        'File',
    ],
)]
class FileReuploadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);
        $data->reupload = $request->getAttribute('id');

        $service = $this->getRecordService('File');

        $id = $service->reuploadEntity($data);
        $entity = $service->prepareEntityById($id);

        return new JsonResponse((array)$entity->getValueMap());
    }
}
