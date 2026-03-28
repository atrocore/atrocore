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
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/action/upload-proxy',
    methods: [
        'POST',
    ],
    summary: 'Proxy file upload from URL',
    description: 'Streams a remote file through the server to the client.',
    tag: 'File',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'url',
                    ],
                    'properties' => [
                        'url' => [
                            'type'   => 'string',
                            'format' => 'uri',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'File content stream',
        ],
    ],
)]
class FileUploadProxyHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'url') || empty($data->url)) {
            throw new BadRequest();
        }

        $fileStream = fopen($data->url, 'r');
        if (!$fileStream) {
            throw new \RuntimeException('Failed to open file stream');
        }

        return new Response(200, ['Content-Type' => 'application/octet-stream'], Utils::streamFor($fileStream));
    }
}
