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
    path: '/File/{id}/createSharedUrl',
    methods: [
        'POST',
    ],
    summary: 'Create a shared URL for a file',
    description: 'Creates a public Sharing record for the given File and returns a `sharedUrl` ready to embed in a rich text editor or share externally.',
    tag: 'File',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the File record to share.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Public sharing URL.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'required'   => ['sharedUrl'],
                        'properties' => [
                            'sharedUrl' => [
                                'type'        => 'string',
                                'description' => 'Public URL for embedding or sharing the file.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'The current user does not have File read permission.',
        ],
        404 => [
            'description' => 'File record not found.',
        ],
    ],
)]
class FileCreateSharedUrlHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->getRecordService('File')->createSharedUrl($request->getAttribute('id'));

        return new JsonResponse($result);
    }
}
