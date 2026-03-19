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
    path: '/{entityName}/action/massRemoveAttribute',
    methods: ['POST'],
    summary: 'Mass remove attribute',
    description: 'Removes an attribute from multiple records of the specified entity.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Entity record', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'])]
class MassRemoveAttributeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'update')) {
            throw new Forbidden();
        }

        $data       = $this->getRequestBody($request);
        $params     = $this->buildMassParams($data);
        $attributes = json_decode(json_encode($data->attributes), true);

        $result = $this->getRecordService($entityName)->massRemoveAttribute($attributes, $params);

        return new JsonResponse(is_array($result) ? $result : ['true' => $result]);
    }
}