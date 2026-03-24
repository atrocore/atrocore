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

namespace Atro\Handlers\Connection;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Connection/{id}',
    methods: ['GET'],
    summary: 'Returns a connection record',
    description: 'Returns a single connection record by ID. Accessible by administrators only.',
    tag: 'Connection',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Connection record', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
class ConnectionReadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $id     = (string) $request->getAttribute('id');
        $entity = $this->getRecordService('Connection')->readEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        return new JsonResponse((array) $entity->getValueMap());
    }
}
