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
    path: '/File',
    methods: [
        'POST',
    ],
    summary: 'Create a File record',
    description: 'Creates a new File entity from uploaded content (`fileContents`), a remote URL (`url`), or a local file path (`localFileName`). Returns the created File record, and optionally `duplicate` if a file with the same content hash already exists, and `sharedUrl` if `share` was requested.',
    tag: 'File',
    parameters: [
        [
            'name'        => 'Skip-Extension-Update',
            'in'          => 'header',
            'required'    => false,
            'description' => 'Set to "true" to prevent AtroCore from automatically correcting the file extension based on its detected MIME type.',
            'schema'      => [
                'type' => 'string',
                'enum' => ['true'],
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
                                    'description' => 'Remote URL to fetch the file from. Mutually exclusive with `fileContents` and `localFileName`.',
                                ],
                                'share'        => [
                                    'type'        => 'boolean',
                                    'description' => 'When true, a Sharing record is created and `sharedUrl` is returned in the response.',
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
            'description' => 'The created File record. Optionally includes `duplicate` (existing file with the same content hash) and `sharedUrl` (public sharing link when `share` was requested).',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            [
                                '$ref' => '#/components/schemas/File',
                            ],
                            [
                                'type'       => 'object',
                                'properties' => [
                                    'duplicate' => [
                                        '$ref'        => '#/components/schemas/File',
                                        'description' => 'Existing File record with the same content hash, if one was found.',
                                    ],
                                    'sharedUrl' => [
                                        'type'        => 'string',
                                        'description' => 'Public sharing URL. Present only when `share` was set in the request.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Invalid or missing fields, invalid URL, or storage not configured.',
        ],
        403 => [
            'description' => 'The current user does not have File create permission.',
        ],
    ],
    entities: [
        'File',
    ],
)]
class FileCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data          = $this->getRequestBody($request);
        $data->fromApi = true;

        $result = $this->getRecordService('File')->createEntityAndBuildResponse($data);

        return new JsonResponse($result);
    }
}
