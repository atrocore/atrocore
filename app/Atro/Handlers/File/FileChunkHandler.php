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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/createChunk',
    methods: [
        'POST',
    ],
    summary: 'Upload a file chunk',
    description: 'Uploads a single chunk of a multi-part file upload. Call repeatedly with each chunk. While chunks are still pending, returns `{chunks: string[]}`. When the final chunk is received, returns the full File record merged with `{chunks: string[]}`, and optionally `{duplicate: object}` if a file with the same hash already exists.',
    tag: 'File',
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
                            'type'     => 'object',
                            'required' => ['id', 'piecesCount'],
                            'properties' => [
                                'id'          => [
                                    'type'        => 'string',
                                    'description' => 'Client-generated file ID, consistent across all chunks of the same upload',
                                ],
                                'piece'       => [
                                    'type'        => 'string',
                                    'description' => 'Base64-encoded chunk data',
                                ],
                                'piecesCount' => [
                                    'type'        => 'integer',
                                    'minimum'     => 1,
                                    'description' => 'Total number of chunks the file is split into',
                                ],
                                'reupload'    => [
                                    'type'        => 'string',
                                    'description' => 'ID of an existing File record to replace. When set, the upload overwrites the existing file content.',
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
            'description' => 'While chunks are still pending: only `chunks` is present. On the final chunk: full File record merged with `chunks`, and optionally `duplicate` and `sharedUrl`.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            [
                                '$ref' => '#/components/schemas/File',
                            ],
                            [
                                'type'       => 'object',
                                'required'   => ['chunks'],
                                'properties' => [
                                    'chunks'    => [
                                        'type'        => 'array',
                                        'description' => 'List of chunk identifiers received so far. Present in every response.',
                                        'items'       => ['type' => 'string'],
                                    ],
                                    'duplicate' => [
                                        '$ref'        => '#/components/schemas/File',
                                        'description' => 'Existing File record with the same content hash, if one was found. Present only on completion.',
                                    ],
                                    'sharedUrl' => [
                                        'type'        => 'string',
                                        'description' => 'Public sharing URL. Present only on completion when `share` was set in the request.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Missing required field (`id` or `piecesCount`), storage not configured, or field validation failure.',
        ],
        403 => [
            'description' => 'The current user does not have File create or edit permission.',
        ],
    ],
    entities: [
        'File',
    ],
)]
class FileChunkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $service = $this->getRecordService('File');

        $result = $service->createChunk($data);

        return new JsonResponse($result);
    }
}
