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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/action/restore',
    methods: ['POST'],
    summary: 'Restore a record',
    description: 'Restores a soft-deleted record from the recycle bin.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'])]
class RestoreHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (!property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $result = $this->getRecordService($entityName)->restoreEntity((string) $data->id);

        return new JsonResponse(['true' => !empty($result)]);
    }
}