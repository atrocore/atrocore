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
    path: '/{entityName}/{id}/subscription',
    methods: ['DELETE'],
    summary: 'Unfollow stream',
    description: 'Unsubscribes the current user from the stream of the specified entity record.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id',         'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'])]
class UnfollowHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $result = $this->getRecordService($entityName)->unfollow($id);

        return new JsonResponse(['true' => $result]);
    }
}