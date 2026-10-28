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

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/{id}/erasePersonalData',
    methods: [
        'POST',
    ],
    summary: 'Erase personal data of a record',
    description: 'Erases the personal data fields of the specified record according to the erasure mode of each field. Available for global administrators and data privacy administrators only.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name that contains personal data (e.g. "Person")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the record whose personal data will be erased',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => '`true` when the personal data of the record is erased',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user is neither a global administrator nor a data privacy administrator',
        ],
        404 => [
            'description' => 'Not found — no record with the given ID exists',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['containsPersonalData'])]
class ErasePersonalDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id = $request->getAttribute('id');

        $result = $this->getRecordService($entityName)->erasePersonalData($id);

        return new BoolResponse($result);
    }
}
