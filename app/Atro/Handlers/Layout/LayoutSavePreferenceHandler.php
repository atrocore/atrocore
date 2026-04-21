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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Layout/savePreference',
    methods: [
        'POST',
    ],
    summary: 'Save layout profile preference',
    description: 'Saves the current user\'s preferred layout profile for a specific entity and view type.',
    tag: 'Layout',
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
                        'entityName'      => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. `Product`, `Category`)',
                            'example'     => 'Product',
                        ],
                        'viewType'        => [
                            'type'        => 'string',
                            'description' => 'Layout view type (e.g. `list`, `detail`, `relationships`)',
                            'example'     => 'list',
                        ],
                        'relatedScope'    => [
                            'type'        => 'string',
                            'nullable'    => true,
                            'description' => 'Related entity scope, optionally dot-separated with the link name (e.g. `Category` or `Category.products`)',
                            'example'     => 'Category',
                        ],
                        'layoutProfileId' => [
                            'type'        => 'string',
                            'nullable'    => true,
                            'description' => 'ID of the layout profile to set as preferred. Pass `null` to clear the preference.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Preference saved successfully',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName is missing or viewType is missing',
        ],
    ],
)]
class LayoutSavePreferenceHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $relatedEntity   = '';
        $relatedLink     = '';
        $layoutProfileId = null;

        if (!empty($data->relatedScope)) {
            $parts         = explode('.', $data->relatedScope);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? '';
        }

        if (!empty($data->layoutProfileId)) {
            $layoutProfileId = $data->layoutProfileId;
        }

        $this->getLayoutManager()->saveUserPreference(
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
