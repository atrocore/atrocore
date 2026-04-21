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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRelationBulk',
    methods: [
        'DELETE',
    ],
    summary: 'Remove relations in bulk (synchronous)',
    description: 'Removes relations between records using explicit IDs. Synchronous — returns immediately with the result. For large batches, prefer DELETE /entityRelationBulkAsync to avoid request timeouts.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'link',
                        'ids',
                        'foreignIds',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product")',
                        ],
                        'link'       => [
                            'type'        => 'string',
                            'description' => 'Relation link name (e.g. "categories")',
                        ],
                        'ids'        => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'string',
                            ],
                            'description' => 'IDs of main entity records.',
                        ],
                        'foreignIds' => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'string',
                            ],
                            'description' => 'IDs of foreign entity records to unlink.',
                        ],
                        'data'       => [
                            'type'        => 'object',
                            'description' => 'Extra relation attributes used to narrow which relations to remove',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Operation result',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'required'   => [
                            'message',
                        ],
                        'properties' => [
                            'message' => [
                                'type'        => 'string',
                                'description' => 'Human-readable result message displayed to the user',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName, link, ids or foreignIds are missing',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class EntityRelationBulkDeleteHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data       = $this->getRequestBody($request);
        $entityName = (string)($data->entityName ?? '');
        $link       = (string)($data->link ?? '');

        if (empty($entityName) || empty($link)) {
            throw new BadRequest('entityName and link are required');
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $ids        = $data->ids ?? [];
        $foreignIds = $data->foreignIds ?? [];

        if (!is_array($ids) || !is_array($foreignIds)) {
            throw new BadRequest('ids and foreignIds must be arrays');
        }

        $relationData = json_decode(json_encode($data->data), true);

        return new JsonResponse($this->getServiceFactory()->create('MassActions')->removeRelation($ids, $foreignIds, $entityName, $link, $relationData));
    }
}
