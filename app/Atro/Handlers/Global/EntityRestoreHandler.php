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

namespace Atro\Handlers\Global;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRestore',
    methods: [
        'POST',
    ],
    summary: 'Restore records',
    description: 'Restores one or multiple soft-deleted records from the recycle bin synchronously by their IDs.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'ids',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'ids'        => [
                            'type'        => 'array',
                            'description' => 'IDs of the records to restore.',
                            'items'       => [
                                'type' => 'string',
                                'example' => 'some-id',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Restore result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'restored' => [
                                'type'        => 'integer',
                                'description' => 'Number of successfully restored records.',
                            ],
                            'errors'   => [
                                'type'        => 'array',
                                'description' => 'List of error messages for records that could not be restored.',
                                'items'       => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'IDs count exceeds the configured limit',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class EntityRestoreHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $ids = $data->ids ?? [];
        $limit = $this->getConfig()->get('massRestoreMaxCountWithoutJob', 200);
        if (count($ids) > $limit) {
            throw new BadRequest("Too many ids: maximum allowed is $limit. Use /entityRestoreAsync for large batches.");
        }

        $result = $this->getRecordService($data->entityName)->massRestore($this->buildMassParams($data));

        return new JsonResponse(['restored' => $result['count'], 'errors' => $result['errors']]);
    }
}
