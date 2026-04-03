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
    path: '/{scope}/layout/{viewType}',
    methods: [
        'GET',
    ],
    summary: 'Get layout content',
    description: 'Returns the layout configuration for an entity and view type.',
    tag: 'Layout',
    parameters: [
        [
            'name'     => 'scope',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type'    => 'string',
                'example' => 'Product',
            ],
        ],
        [
            'name'     => 'viewType',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type'    => 'string',
                'example' => 'list',
            ],
        ],
        [
            'name'     => 'relatedScope',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'string',
                'example' => 'Category',
            ],
        ],
        [
            'name'     => 'layoutProfileId',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'isAdminPage',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'string',
                'example' => 'true',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Layout content',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
    ],
)]
class LayoutGetContentHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $scope    = (string) $request->getAttribute('scope');
        $viewType = (string) $request->getAttribute('viewType');
        $qp       = $request->getQueryParams();

        $relatedEntity = null;
        $relatedLink   = null;

        if (!empty($qp['relatedScope'])) {
            $parts         = explode('.', $qp['relatedScope']);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? null;
        }

        $data = $this->getLayoutManager()->get(
            $scope,
            $viewType,
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
