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
    path: '/Layout/detail',
    methods: [
        'GET',
    ],
    summary: 'Get detail layout for an entity',
    description: 'Returns the detail layout for the given entity as an ordered list of sections. Each section has an optional label and a grid of rows; each row contains one or two field cells (`LayoutRowItem`) or `false` for an empty placeholder.

**How to use:**
- `entityName` — the entity whose detail layout is requested (e.g. `Product`).
- `relatedScope` — optional, dot-separated `RelatedEntity.linkName`. When provided, returns the detail layout used when opening the record from inside that relationship panel.
- `layoutName` — optional custom layout name for layouts defined in `clientDefs.{entityName}.additionalLayouts` that map to type `detail` (e.g. `upload`). When omitted, the standard detail layout is returned.
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
            'description' => 'Custom layout name from `additionalLayouts` (e.g. `upload`). Overrides the default `detail` view type.',
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
            'description' => 'Detail layout for the requested entity',
            'content'     => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/LayoutSectionResponse'],
                ],
            ],
        ],
        404 => [
            'description' => 'Layout not found',
        ],
    ],
    entities: [
        'LayoutSection'
    ]
)]
class LayoutDetailHandler extends AbstractHandler
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

        $viewType = $qp['layoutName'] ?? 'detail';

        $data = $this->getLayoutManager()->get(
            $qp['entityName'],
            $viewType,
            $relatedEntity,
            $relatedLink,
            $qp['layoutProfileId'] ?? null,
            ($qp['isAdminPage'] ?? null) === 'true'
        );

        if (empty($data)) {
            throw new NotFound("Layout '{$viewType}' for '{$qp['entityName']}' is not found.");
        }

        return new JsonResponse($data);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
