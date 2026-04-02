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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/action/reupload',
    methods: [
        'PATCH',
    ],
    summary: 'Reupload file content',
    description: 'Reuploads the content for an existing File entity.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'reupload',
                    ],
                    'properties' => [
                        'reupload'     => [
                            'type' => 'string',
                        ],
                        'fileContents' => [
                            'type' => 'string',
                        ],
                        'piece'        => [
                            'type'        => 'string',
                            'description' => 'Chunk data',
                        ],
                        'piecesCount'  => [
                            'type'        => 'integer',
                            'minimum'     => 1,
                            'description' => 'Total number of chunks',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Updated file record',
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
class FileReuploadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'reupload') || empty($data->reupload)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('File', 'edit')) {
            throw new Forbidden();
        }

        $service = $this->getRecordService('File');

        $id = $service->createEntity($data);

        return new JsonResponse((array) $service->readEntity($id)->getValueMap());
    }
}
