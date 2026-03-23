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

use Atro\Core\Exceptions\Error;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}',
    methods: ['DELETE'],
    summary: 'Deletes a record',
    description: 'Deletes a record by ID. Pass the permanently header to skip the recycle bin.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id',         'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'permanently', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => false]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], excludeEntities: ['UserProfile', 'MatchedRecord', 'Matching', 'MasterDataEntity', 'AuthToken'])]
class DeleteHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');

        $service = $this->getRecordService($entityName);

        $permanently = trim((string) $service::getHeader('permanently'));
        $method      = ($permanently && (strtolower($permanently) === 'true' || $permanently === '1'))
            ? 'deleteEntityPermanently'
            : 'deleteEntity';

        if (!$service->$method($id)) {
            throw new Error();
        }

        return new JsonResponse(['true' => true]);
    }
}