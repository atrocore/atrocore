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
    path: '/{scope}/layout/{viewType}',
    methods: [
        'PATCH',
    ],
    summary: 'Update layout content',
    description: 'Saves the layout configuration for an entity and view type.',
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
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'anyOf' => [
                        [
                            'type' => 'array',
                        ],
                        [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Updated layout content',
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
class LayoutUpdateContentHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $scope    = (string) $request->getAttribute('scope');
        $viewType = (string) $request->getAttribute('viewType');
        $qp       = $request->getQueryParams();

        $layoutProfileId = (string) ($qp['layoutProfileId'] ?? '');

        if (empty($layoutProfileId)) {
            throw new BadRequest();
        }

        $relatedEntity = null;
        $relatedLink   = null;

        if (!empty($qp['relatedScope'])) {
            $parts         = explode('.', $qp['relatedScope']);
            $relatedEntity = $parts[0];
            $relatedLink   = $parts[1] ?? null;
        }

        $body = (string) $request->getBody();
        $data = $body !== '' ? json_decode($body, true) : [];
        if (!is_array($data)) {
            $data = (array) json_decode($body);
        }

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile($layoutProfileId);

        $result = $layoutManager->save(
            $scope,
            $viewType,
            (string) $relatedEntity,
            (string) $relatedLink,
            $layoutProfileId,
            $data
        );

        if ($result === false) {
            throw new Error('Error while saving layout.');
        }

        $this->getDataManager()->clearCache(true);

        return new JsonResponse($layoutManager->get($scope, $viewType, $relatedEntity, $relatedLink, $layoutProfileId));
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
