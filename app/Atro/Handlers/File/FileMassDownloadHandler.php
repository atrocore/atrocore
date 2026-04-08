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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/File/massDownload',
    methods: [
        'POST',
    ],
    summary: 'Mass download files',
    description: 'Enqueues a background job that packages the selected files into a ZIP archive. Returns `true` when the job has been successfully created.',
    tag: 'File',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'idList'  => [
                            'type'        => 'array',
                            'description' => 'IDs of the File records to download. Used when `byWhere` is false or absent.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'where'   => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting which files to download. Used when `byWhere` is true.',
                            'items'       => [
                                'type' => 'object',
                            ],
                        ],
                        'byWhere' => [
                            'type'        => 'boolean',
                            'description' => 'When true, `where` is used to select files instead of `idList`.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => '`true` if the mass-download job was successfully created.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'The current user does not have File read permission.',
        ],
    ],
)]
class FileMassDownloadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data   = $this->getRequestBody($request);
        $params = [];

        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        return new BoolResponse($this->getRecordService('File')->massDownload($params));
    }
}
