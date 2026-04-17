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

use Atro\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LayoutProfile/{id}/updateLayout',
    methods: [
        'POST',
    ],
    summary: 'Update entity layout content or a layout profile',
    description: 'Saves the layout configuration for a given entity name and view type into the specified layout profile.',
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
                        'layout',
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
                            'description' => 'Related entity scope, optionally dot-separated with the link name (e.g. `Category` or `Category.products`)',
                            'example'     => 'Category',
                        ],
                        'layout'       => [
                            '$ref' => '#/components/schemas/_LayoutItems',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Updated layout content. Same structure as GET /entityLayout.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/_LayoutData',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or viewType or layout is missing',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
        404 => [
            'description' => 'Layout profile not found',
        ],
    ],
)]
class LayoutProfileUpdateLayoutHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $layoutProfileId = (string)$request->getAttribute('id');

        $data = $this->getRequestBody($request);

        $relatedEntity = null;
        $relatedLink   = null;

        if (!empty($data->relatedScope)) {
            $parts         = explode('.', $data->relatedScope);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? null;
        }

        $layout = isset($data->layout) && is_array($data->layout) ? json_decode(json_encode($data->layout), true): [];

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile($layoutProfileId);

        $result = $layoutManager->save(
            $data->entityName,
            $data->viewType,
            $relatedEntity ?? '',
            $relatedLink ?? '',
            $layoutProfileId,
            $layout
        );

        if ($result === false) {
            throw new Error('Error while saving layout.');
        }

        $this->getDataManager()->clearCache(true);

        return new JsonResponse($layoutManager->get($data->entityName, $data->viewType, $relatedEntity ?? '', $relatedLink ?? '', $layoutProfileId));
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }

    private function getDataManager(): DataManager
    {
        return $this->container->get('dataManager');
    }
}
