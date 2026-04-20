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
                    'schema' => ['$ref' => '#/components/schemas/LayoutSectionResponse'],
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
    entities: [
        'LayoutSection'
    ]
)]
class LayoutProfileUpdateDetailLayoutHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);
        [$relatedEntity, $relatedLink] = $this->parseRelatedScope($data->relatedScope ?? null);

        return new JsonResponse(
            $this->getRecordService('LayoutProfile')->updateLayout(
                $request->getAttribute('id'),
                $data->entityName,
                $data->layoutName ?? 'detail',
                $relatedEntity,
                $relatedLink,
                json_decode(json_encode($data->layout ?? []), true)
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

}
