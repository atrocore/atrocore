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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/action/massDelete',
    methods: ['POST'],
    summary: 'Mass delete',
    description: 'Deletes multiple records of the specified entity.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], excludeEntities: ['UserProfile', 'MatchedRecord', 'Matching', 'MasterDataEntity', 'AuthToken'])]
class MassDeleteHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'delete')) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $params = $this->buildMassParams($data);

        if (property_exists($data, 'permanently')) {
            $params['permanently'] = $data->permanently;
        }

        $result = $this->getRecordService($entityName)->massRemove($params);

        return new JsonResponse(is_array($result) ? $result : ['true' => $result]);
    }
}