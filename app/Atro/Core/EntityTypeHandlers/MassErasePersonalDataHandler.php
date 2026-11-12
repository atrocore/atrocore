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

namespace Atro\Core\EntityTypeHandlers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Core\Services\Interfaces\PersonalDataErasableInterface;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/massErasePersonalData',
    methods: [
        'POST',
    ],
    summary: 'Mass erase personal data (synchronous)',
    description: 'Erases the personal data fields of multiple records synchronously according to the erasure mode of each field and returns the result immediately. Available for global administrators and data privacy administrators only.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name that contains personal data',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'ids',
                    ],
                    'properties' => [
                        'ids' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'IDs of the records.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'erased' => [
                                'type' => 'integer',
                            ],
                            'errors' => [
                                'type'        => 'array',
                                'description' => 'List of error messages for records that could not be erased.',
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
            'description' => 'ids are missing, or ids count exceeds the configured limit',
        ],
        403 => [
            'description' => 'Forbidden — the current user is neither a global administrator nor a data privacy administrator',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Archive', 'Relation'], requires: ['containsPersonalData'])]
class MassErasePersonalDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (empty($data->ids)) {
            throw new BadRequest('ids is required');
        }

        $limit = $this->getConfig()->get('massErasePersonalDataMaxCountWithoutJob', 200);
        if (count($data->ids) > $limit) {
            throw new BadRequest("Too many ids: maximum allowed is $limit. Use /$entityName/massErasePersonalDataAsync for large batches.");
        }

        $params = ['ids' => $data->ids, 'maxCountWithoutJob' => PHP_INT_MAX];
        $result = $this->getRecordServiceWith($entityName, PersonalDataErasableInterface::class)->massErasePersonalData($params);

        return new JsonResponse(['erased' => $result['count'], 'errors' => $result['errors']]);
    }
}
