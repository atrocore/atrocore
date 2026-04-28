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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/dynamicActions',
    methods: [
        'GET',
    ],
    summary: 'Get dynamic actions for an entity or a record',
    description: 'Returns the list of available dynamic actions. '
        . 'When id is provided, actions are evaluated against that specific record state and may include the bookmark toggle. '
        . 'When id is omitted, actions are returned for the entity as a whole (list/mass-action context). '
        . 'Actions are filtered by the current user\'s ACL and the optional display context.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Entity name (e.g. "Product", "Category").',
            'schema'      => [
                'type'    => 'string',
                'example' => 'Product',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Record ID. When provided, actions are evaluated against the record state.',
            'schema'      => [
                'type'    => 'string',
                'example' => 'a01jz56xg5xe09abkmfg4dr0kvj',
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
            'description' => 'List of available dynamic actions.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'action'       => [
                                    'type'        => 'string',
                                    'description' => 'Action identifier. "dynamicAction" for workflow-based actions, "bookmark" for the bookmark toggle.',
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
                                        'action_id'   => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'ID of the Action record (workflow action).',
                                        ],
                                        'entity_id'   => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'ID of the entity record this action targets. Null when id is not provided.',
                                        ],
                                        'bookmark_id' => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'ID of the existing Bookmark record. Present only for the bookmark action; null when not yet bookmarked.',
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
        404 => [
            'description' => 'Record not found — only possible when id is provided.',
        ],
    ],
    skipActionHistory: true,
)]
class DynamicActionsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query      = $request->getQueryParams();
        $entityName = (string) ($query['entityName'] ?? '');
        $id         = (string) ($query['id'] ?? '');

        /** @var \Atro\Services\Action $service */
        $service = $this->getServiceFactory()->create('Action');

        return new JsonResponse(
            $service->getDynamicActions(
                $entityName,
                $id !== '' ? $id : null,
                $query['type'] ?? null,
                $query['display'] ?? null
            )
        );
    }
}
