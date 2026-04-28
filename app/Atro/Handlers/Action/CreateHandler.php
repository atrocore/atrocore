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

namespace Atro\Handlers\Action;

use Atro\Core\Routing\Route;

#[Route(
    path: '/Action/{id}/create',
    methods: [
        'POST',
    ],
    summary: 'Execute Create action',
    description: 'Executes the specified Create action synchronously. Creates one or more entity records according to the action configuration.',
    tag: 'Action',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Action record ID.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'entityId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the source entity record used as context for condition evaluation and template rendering.',
                        ],
                        'where'      => [
                            'type'        => 'array',
                            'description' => 'Filter conditions that select the source records to iterate over. Provide together with `massAction: true`.',
                            'items'       => ['type' => 'object'],
                        ],
                        'massAction' => [
                            'type'        => 'boolean',
                            'description' => 'Set to `true` to apply the action to all records matching the `where` filter.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Execution result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                            ],
                            'message' => [
                                'type'     => 'string',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Action record not found.',
        ],
    ],
)]
class CreateHandler extends AbstractActionTypeSyncHandler
{
}
