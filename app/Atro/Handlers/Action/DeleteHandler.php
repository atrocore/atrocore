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
    path: '/Action/{id}/delete',
    methods: [
        'POST',
    ],
    summary: 'Execute Delete action',
    description: 'Executes the specified Delete action synchronously. Deletes one or more entity records according to the action configuration.',
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
                        'entityId' => [
                            'type'        => 'string',
                            'description' => 'ID of the entity record to delete. Required when `applyToPreselectedRecords` is enabled on the action.',
                        ],
                        'where'    => [
                            'type'        => 'array',
                            'description' => 'Filter conditions for mass delete. All records matching the filter are deleted.',
                            'items'       => ['type' => 'object'],
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
class DeleteHandler extends AbstractActionTypeSyncHandler
{
}
