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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}/updateMasterRecord',
    methods: [
        'POST',
    ],
    summary: 'Propagate staging record to master',
    description: 'Applies all changes from the specified staging record back to its master record.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name that supports master/staging workflow (e.g. "Product")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the staging record whose changes will be propagated to the master record',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the master record was successfully updated',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to this entity type',
        ],
        404 => [
            'description' => 'Not found — no staging record with the given ID exists',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['primaryEntityId'])]
class UpdateMasterRecordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $staging = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($staging)) {
            throw new NotFound();
        }

        $this->getServiceFactory()->create('MasterDataEntity')->updateMasterRecord($staging);

        return new BoolResponse(true);
    }
}