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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/action/massDownload',
    methods: [
        'POST',
    ],
    summary: 'Mass download files',
    description: 'Initiates a mass download for multiple files, returning a download link.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'idList'     => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'where'      => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'object',
                            ],
                        ],
                        'byWhere'    => [
                            'type' => 'boolean',
                        ],
                        'selectData' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Download result',
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
class FileMassDownloadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('File', 'read')) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $params = [];

        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $params['where'] = json_decode(json_encode($data->where), true);
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        return new JsonResponse($this->getRecordService('File')->massDownload($params));
    }
}
