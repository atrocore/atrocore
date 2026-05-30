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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/inheritAllFromParentAsync',
    methods: [
        'POST',
    ],
    summary: 'Inherit all fields and links from parent for multiple records',
    description: 'For each given entity record ID, pulls all inheritable field values and linked records from the parent record into that record. Only fields that are currently null are updated — already-set values are not overwritten.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name (e.g. "Category")',
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
                            'description' => 'IDs of the children records to inherit into',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the background job was successfully created',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'IDs of the children records are missing or empty',
        ],
    ],
)]
#[EntityType(types: ['Hierarchy'])]
class InheritAllFromParentAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (empty($data->ids)) {
            throw new BadRequest();
        }

        $job = $this->getEntityManager()->getEntity('Job');
        $job->set([
            'name'    => $this->getLanguage()->translate('inheritAllFromParent', 'massActions'),
            'type'    => 'InheritAllFromParent',
            'payload' => [
                'entityType' => $entityName,
                'ids'        => array_values($data->ids),
            ],
        ]);
        $this->getEntityManager()->saveEntity($job);

        return new BoolResponse(true);
    }
}
