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
    path: '/File/prepareForRichEditor',
    methods: [
        'POST',
    ],
    summary: 'Prepare file for rich editor',
    description: 'Creates a public Sharing record for the given File and returns the file data with a `sharedUrl` ready to embed in a rich text editor.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'fileId',
                    ],
                    'properties' => [
                        'fileId' => [
                            'type'        => 'string',
                            'description' => 'ID of the File record to share.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Public sharing URL ready to embed in the rich text editor.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'required'   => [
                            'sharedUrl',
                        ],
                        'properties' => [
                            'sharedUrl' => [
                                'type'        => 'string',
                                'description' => 'Public URL for embedding the file in a rich text editor.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => '`fileId` is missing or empty.',
        ],
        403 => [
            'description' => 'The current user does not have File read permission.',
        ],
        404 => [
            'description' => 'File record not found.',
        ],
    ],
)]
class FilePrepareForRichEditorHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $result = $this->getRecordService('File')->prepareForRichEditor($data->fileId);

        return new JsonResponse($result);
    }
}
