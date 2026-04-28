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
    path: '/Action/{id}/createOrUpdateAsync',
    methods: [
        'POST',
    ],
    summary: 'Execute CreateOrUpdate action asynchronously',
    description: 'Schedules the specified CreateOrUpdate action as a background job and returns immediately with the job ID.',
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
            'description' => 'The action has been scheduled as a background job.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'jobId' => [
                                'type'        => 'string',
                                'description' => 'ID of the created background job.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class CreateOrUpdateAsyncHandler extends AbstractActionTypeAsyncHandler
{
}
