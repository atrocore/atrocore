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

namespace Atro\Handlers\User;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/User/avatar',
    methods: [
        'POST',
    ],
    summary: 'Upload own avatar',
    description: 'Uploads a base64 data URL image as the current user\'s avatar.',
    tag: 'User',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['fileContents', 'name', 'filesize'],
                    'properties' => [
                        'fileContents'     => [
                            'type'        => 'string',
                            'description' => 'Base64-encoded avatar file content as a data URI (e.g. `data:image/png;base64,...`).',
                        ],
                        'name' => [
                            'type'        => 'string',
                            'description' => 'Original avatar file name.',
                        ],
                        'filesize' => [
                            'type'        => 'integer',
                            'description' => 'Avatar file size in bytes.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Avatar uploaded successfully.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'No image data provided, unsupported file type or permitted file size has been exceeded.',
        ],
    ],
)]
class UploadAvatarHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $this->getServiceFactory()->create('Avatar')->upload(
            $this->getUser()->get('id'),
            $data->fileContents,
            $data->name,
            $data->filesize
        );

        return new BoolResponse(true);
    }
}