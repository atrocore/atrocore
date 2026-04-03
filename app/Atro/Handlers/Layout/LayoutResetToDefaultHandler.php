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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\LayoutManager;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Layout/action/resetToDefault',
    methods: [
        'POST',
    ],
    summary: 'Reset layout to default',
    description: 'Removes the custom configuration for a layout, reverting it to the default.',
    tag: 'Layout',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'scope',
                        'viewType',
                        'layoutProfileId',
                    ],
                    'properties' => [
                        'scope'           => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'viewType'        => [
                            'type'    => 'string',
                            'example' => 'list',
                        ],
                        'relatedScope'    => [
                            'type'    => 'string',
                            'example' => 'Category',
                        ],
                        'layoutProfileId' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Reset layout content',
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
class LayoutResetToDefaultHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->scope) || empty($data->viewType) || empty($data->layoutProfileId)) {
            throw new BadRequest();
        }

        $relatedEntity = '';
        $relatedLink   = '';

        if (!empty($data->relatedScope)) {
            $parts         = explode('.', $data->relatedScope);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? '';
        }

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile((string) $data->layoutProfileId);

        return new JsonResponse($layoutManager->resetToDefault(
            (string) $data->scope,
            (string) $data->viewType,
            $relatedEntity,
            $relatedLink,
            (string) $data->layoutProfileId
        ));
    }

    private function getLayoutManager(): LayoutManager
    {
        return $this->container->get('layoutManager');
    }
}
