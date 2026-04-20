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

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
#[Route(
    path: '/LayoutProfile/{id}/resetLayoutToDefault',
    methods: [
        'POST',
    ],
    summary: 'Reset a single layout to default',
    description: 'Removes the custom configuration for a specific entity, view type, and layout profile, reverting it to the default layout.',
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
                        'viewType',
                    ],
                    'properties' => [
                        'entityName'   => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. `Product`, `Category`)',
                            'example'     => 'Product',
                        ],
                        'viewType'     => [
                            'type'        => 'string',
                            'description' => 'Layout view type (e.g. `list`, `detail`, `edit`)',
                            'example'     => 'list',
                        ],
                        'relatedScope' => [
                            'type'        => 'string',
                            'nullable'    => true,
                            'description' => 'Related entity scope, optionally dot-separated with the link name (e.g. `Category` or `Category.products`)',
                            'example'     => 'Category',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Layout reset successfully.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or viewType is missing',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
        404 => [
            'description' => 'Layout profile not found',
        ],
    ],
)]
class LayoutProfileResetLayoutToDefaultHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $layoutProfileId = $request->getAttribute('id');

        $data = $this->getRequestBody($request);

        $relatedEntity = '';
        $relatedLink   = '';

        if (!empty($data->relatedScope)) {
            $parts         = explode('.', $data->relatedScope);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? '';
        }

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile($layoutProfileId);

        $layoutManager->resetToDefault(
            $data->entityName,
            $data->viewType,
            $relatedEntity,
            $relatedLink,
            $layoutProfileId
        );

        return new BoolResponse(true);
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
