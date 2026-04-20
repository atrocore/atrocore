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

namespace Atro\Handlers\LayoutProfile;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Atro\Services\LayoutProfile as LayoutProfileService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LayoutProfile/{id}/updateDetailLayout',
    methods: [
        'POST',
    ],
    summary: 'Save detail layout into a layout profile',
    description: 'Saves the detail layout configuration for a given entity into the specified layout profile.',
    tag: 'LayoutProfile',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Layout profile record ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'layout',
                    ],
                    'properties' => [
                        'entityName'   => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. `Product`, `Category`)',
                            'example'     => 'Product',
                        ],
                        'relatedScope' => [
                            'type'        => 'string',
                            'nullable'    => true,
                            'description' => 'Related entity scope, optionally dot-separated with the link name (e.g. `Category.products`)',
                            'example'     => 'Category.products',
                        ],
                        'layoutName'   => [
                            'type'        => 'string',
                            'description' => 'Custom layout name from `additionalLayouts`. When omitted, saves to the default `detail` layout.',
                        ],
                        'layout'       => [
                            'type'  => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/LayoutSection',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Updated detail layout',
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
                                'description' => 'Ordered list of sections, each containing rows of field cells.',
                                'items'       => [
                                    '$ref' => '#/components/schemas/LayoutSection',
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
        400 => [
            'description' => 'entityName or layout is missing',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
        404 => [
            'description' => 'Layout profile not found',
        ],
    ],
)]
class LayoutProfileUpdateDetailLayoutHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);
        [$relatedEntity, $relatedLink] = $this->parseRelatedScope($data->relatedScope ?? null);

        return new JsonResponse(
            $this->getService()->updateLayout(
                (string)$request->getAttribute('id'),
                $data->entityName,
                $data->layoutName ?? 'detail',
                $relatedEntity,
                $relatedLink,
                isset($data->layout) && is_array($data->layout) ? json_decode(json_encode($data->layout), true) : []
            )
        );
    }

    private function parseRelatedScope(?string $relatedScope): array
    {
        if (empty($relatedScope)) {
            return ['', ''];
        }
        $parts = explode('.', $relatedScope);
        return [$parts[0], $parts[1] ?? ''];
    }

    private function getService(): LayoutProfileService
    {
        return $this->container->get('serviceFactory')->create('LayoutProfile');
    }
}
