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

namespace Atro\Handlers\Global;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityLayout',
    methods: [
        'GET',
    ],
    summary: 'Get entity layout content',
    description: 'Returns the layout configuration for a given entity name and view type.',
    tag: 'Global',
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
            'name'        => 'viewType',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Layout view type (e.g. `list`, `detail`, `relationships`)',
            'schema'      => [
                'type'    => 'string',
                'example' => 'list',
            ],
        ],
        [
            'name'        => 'relatedScope',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Related entity scope, optionally dot-separated with the link name (e.g. `Category` or `Category.products`)',
            'schema'      => [
                'type'    => 'string',
                'example' => 'Category',
            ],
        ],
        [
            'name'        => 'layoutProfileId',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Layout profile ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'isAdminPage',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Set to `true` to retrieve the admin-page variant of the layout',
            'schema'      => [
                'type'    => 'string',
                'example' => 'true',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Layout configuration for the requested entity and view type',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/_LayoutData',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or viewType is missing',
        ],
        404 => [
            'description' => 'Layout not found',
        ],
    ],
)]
class EntityLayoutHandler extends AbstractHandler
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

        $data = $this->getLayoutManager()->get(
            $qp['entityName'],
            $qp['viewType'],
            $relatedEntity,
            $relatedLink,
            $qp['layoutProfileId'] ?? null,
            ($qp['isAdminPage'] ?? null) === 'true'
        );

        if (empty($data)) {
            throw new NotFound("Layout {$scope}:{$viewType} is not found.");
        }

        return new JsonResponse($data);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
