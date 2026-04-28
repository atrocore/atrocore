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
    path: '/Action/{id}/set',
    methods: [
        'POST',
    ],
    summary: 'Execute Set action',
    description: 'Executes the specified Set action synchronously. Runs the ordered sequence of sub-actions configured in the set, one after another.',
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
                            'description' => 'ID of the source entity record passed as context to each sub-action in the set.',
                        ],
                        'where'      => [
                            'type'        => 'array',
                            'description' => 'Filter conditions for mass execution. Provide together with `massAction: true` to run all sub-actions against every matching record.',
                            'items'       => ['type' => 'object'],
                        ],
                        'massAction' => [
                            'type'        => 'boolean',
                            'description' => 'Set to `true` to apply all sub-actions to every record matching the `where` filter.',
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
class SetHandler extends AbstractActionTypeSyncHandler
{
}
