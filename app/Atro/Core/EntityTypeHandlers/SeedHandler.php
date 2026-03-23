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
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/action/seed',
    methods: ['GET'],
    summary: 'Get seed data',
    description: 'Returns default field values rendered via Twig for a new entity record.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Entity record', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation', 'ReferenceData'], excludeEntities: ['UserProfile', 'AuthToken', 'Connection'])]
class SeedHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $seed   = $this->entityManager->getRepository($entityName)->get();
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $seed->getEntityType(), 'fields'], []) as $name => $defs) {
            if (
                !empty($defs['type']) && $defs['type'] === 'varchar' &&
                !empty($defs['default']) && $seed->has($name)
            ) {
                $default = $defs['default'];
                if (strpos($default, '{{') !== false && strpos($default, '}}') !== false) {
                    $result[$name] = $seed->get($name);
                }
            }
        }

        return new JsonResponse($result);
    }
}