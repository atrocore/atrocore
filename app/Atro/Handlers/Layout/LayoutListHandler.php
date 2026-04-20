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
    path: '/Layout/list',
    methods: [
        'GET',
    ],
    summary: 'Get list layout for an entity',
    description: 'Returns the list layout for the given entity as an ordered array of column definitions.

**How to use:**
- `entityName` — the entity whose list layout is requested (e.g. `Product`).
- `relatedScope` — optional, dot-separated `RelatedEntity.linkName` (e.g. `Category.products`). When provided, returns the list layout used inside that relationship panel.
- `layoutName` — optional custom layout name for layouts defined in `clientDefs.{entityName}.additionalLayouts` that map to type `list` (e.g. `listSmall`). When omitted, the standard list layout is returned.
- `layoutProfileId` — optional layout profile ID. Falls back to the user\'s active profile.
- `isAdminPage` — when `true`, multi-language field injection is skipped.',
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
            'name'        => 'relatedScope',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Related entity scope, optionally dot-separated with the link name (e.g. `Category.products`)',
            'schema'      => [
                'type'    => 'string',
                'example' => 'Category.products',
            ],
        ],
        [
            'name'        => 'layoutName',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Custom layout name from `additionalLayouts` (e.g. `listSmall`). Overrides the default `list` view type.',
            'schema'      => [
                'type' => 'string',
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
            'description' => 'Set to `true` to skip multi-language field injection (admin layout editor use case)',
            'schema'      => [
                'type'    => 'string',
                'example' => 'true',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'List layout for the requested entity',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'required'   => [
                            'layout',
                            'storedProfile',
                            'storedProfiles',
                            'selectedProfileId',
                            'canEdit',
                        ],
                        'properties' => [
                            'layout'            => [
                                'type'        => 'array',
                                'description' => 'Ordered list of column definitions.',
                                'items'       => [
                                    '$ref' => '#/components/schemas/LayoutListItem',
                                ],
                            ],
                            'storedProfile'     => [
                                'type'        => 'object',
                                'nullable'    => true,
                                'description' => 'Layout profile this layout belongs to, or null for the default.',
                                'required'    => ['id', 'name'],
                                'properties'  => [
                                    'id'   => [
                                        'type' => 'string',
                                    ],
                                    'name' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                            'storedProfiles'    => [
                                'type'        => 'array',
                                'description' => 'All profiles that have a stored layout for this entity and view type.',
                                'items'       => [
                                    'type'       => 'object',
                                    'required'   => ['id', 'name'],
                                    'properties' => [
                                        'id'   => [
                                            'type' => 'string',
                                        ],
                                        'name' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                            'selectedProfileId' => [
                                'nullable'    => true,
                                'type'        => 'string',
                                'description' => 'Profile ID selected by the current user, or null.',
                            ],
                            'canEdit'           => [
                                'type'        => 'boolean',
                                'description' => 'Whether the current user may edit the active layout profile.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Layout not found',
        ],
    ],
)]
class LayoutListHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        $relatedEntity = null;
        $relatedLink   = null;

        if (!empty($qp['relatedScope'])) {
            $parts         = explode('.', $qp['relatedScope']);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? null;
        }

        $viewType = $qp['layoutName'] ?? 'list';

        $data = $this->getLayoutManager()->get(
            $qp['entityName'],
            $viewType,
            $relatedEntity,
            $relatedLink,
            $qp['layoutProfileId'] ?? null,
            ($qp['isAdminPage'] ?? null) === 'true'
        );

        if (empty($data)) {
            throw new NotFound("List layout for '{$qp['entityName']}' is not found.");
        }

        return new JsonResponse($data);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
