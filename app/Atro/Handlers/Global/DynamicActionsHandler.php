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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/dynamicActions',
    methods: [
        'GET',
    ],
    summary: 'Get dynamic actions for an entity',
    description: 'Returns the list of available dynamic actions for the specified entity. '
        . 'Used in list view and mass-action context where no specific record is selected. '
        . 'Actions are filtered by the current user\'s ACL and the optional display context.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name (e.g. "Product", "Category").',
            'schema'      => [
                'type'    => 'string',
                'example' => 'Product',
            ],
        ],
        [
            'name'        => 'type',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Action context type. Use "record" for record-level actions or "field" for field-level actions.',
            'schema'      => [
                'type' => 'string',
                'enum' => [
                    'record',
                    'field',
                ],
            ],
        ],
        [
            'name'        => 'display',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Display context filter. When provided, only actions whose "display" property matches this value are returned.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'detail',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'List of available dynamic actions for the entity.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'action'       => [
                                    'type'        => 'string',
                                    'description' => 'Action identifier. Always "dynamicAction" for workflow-based actions.',
                                    'example'     => 'dynamicAction',
                                ],
                                'label'        => [
                                    'type'        => 'string',
                                    'description' => 'Human-readable action label shown in the UI.',
                                    'example'     => 'Send notification',
                                ],
                                'type'         => [
                                    'type'        => 'string',
                                    'nullable'    => true,
                                    'description' => 'Action type (e.g. "previewTemplate"). Null for standard workflow actions.',
                                ],
                                'display'      => [
                                    'type'        => 'string',
                                    'nullable'    => true,
                                    'description' => 'Display context in which this action should be rendered.',
                                ],
                                'html'         => [
                                    'type'        => 'string',
                                    'nullable'    => true,
                                    'description' => 'Optional HTML markup for a custom action button.',
                                ],
                                'tooltip'      => [
                                    'type'        => 'string',
                                    'nullable'    => true,
                                    'description' => 'Optional tooltip text shown on hover.',
                                ],
                                'displayField' => [
                                    'type'        => 'string',
                                    'nullable'    => true,
                                    'description' => 'Field name this action is bound to. Present only for field-type actions.',
                                ],
                                'data'         => [
                                    'type'        => 'object',
                                    'description' => 'Action payload consumed by the frontend action handler.',
                                    'properties'  => [
                                        'action_id' => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'ID of the Action record (workflow action).',
                                        ],
                                        'entity_id' => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'Always null for entity-level actions.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'The current user does not have read access to the entity, or actions are disabled for it.',
        ],
    ],
    skipActionHistory: true,
)]
class DynamicActionsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = (string)$request->getAttribute('entityName');
        $query = $request->getQueryParams();

        /** @var \Atro\Services\Action $service */
        $service = $this->getServiceFactory()->create('Action');

        return new JsonResponse(
            $service->getDynamicActions(
                $entityName,
                null,
                $query['type'] ?? null,
                $query['display'] ?? null
            )
        );
    }
}
