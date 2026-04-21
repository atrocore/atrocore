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

namespace Atro\Handlers\Layout;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Layout/relationships',
    methods: [
        'GET',
    ],
    summary: 'Get relationships layout for an entity',
    description: 'Returns the relationships layout for the given entity as an ordered list of relationship panel entries.

**How to use:**
- `entityName` — the entity whose relationships layout is requested.
- `layoutProfileId` — optional layout profile ID.',
    tag: 'Layout',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Entity name (e.g. `Product`, `Category`)',
            'schema'      => [
                'type'    => 'string',
                'example' => 'Product',
            ],
        ],
        [
            'name'        => 'layoutProfileId',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Layout profile ID. Falls back to the user\'s active profile when omitted.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'isAdminPage',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Set to `true` to skip multi-language field injection.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'true',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Relationships layout for the requested entity',
            'content'     => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/LayoutRelationshipItemResponse'],
                ],
            ],
        ],
        404 => [
            'description' => 'Layout not found',
        ],
    ],
    entities: [
        'LayoutRelationshipItem'
    ]
)]
class LayoutRelationshipsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        $data = $this->getLayoutManager()->get(
            $qp['entityName'],
            'relationships',
            null,
            null,
            $qp['layoutProfileId'] ?? null,
            ($qp['isAdminPage'] ?? null) === 'true'
        );

        if (empty($data)) {
            throw new NotFound("Relationships layout for '{$qp['entityName']}' is not found.");
        }

        return new JsonResponse($data);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
