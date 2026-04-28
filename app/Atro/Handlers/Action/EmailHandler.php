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
    path: '/Action/{id}/email',
    methods: [
        'POST',
    ],
    summary: 'Execute Email action',
    description: 'Executes the specified Email action synchronously. Sends an email according to the action configuration. '
        . 'Optional body fields can override template values such as subject, body, and recipient lists.',
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
                    'type'        => 'object',
                    'description' => 'Optional overrides for the email action. All fields are optional and supplement the action configuration.',
                    'properties'  => [
                        'subject'   => [
                            'type'        => 'string',
                            'description' => 'Override the email subject.',
                        ],
                        'body'      => [
                            'type'        => 'string',
                            'description' => 'Override the email body.',
                        ],
                        'emailTo'   => [
                            'type'        => 'array',
                            'description' => 'Override the TO recipient list.',
                            'items'       => ['type' => 'string'],
                        ],
                        'emailCc'   => [
                            'type'        => 'array',
                            'description' => 'Override the CC recipient list.',
                            'items'       => ['type' => 'string'],
                        ],
                        'emailBcc'  => [
                            'type'        => 'array',
                            'description' => 'Override the BCC recipient list.',
                            'items'       => ['type' => 'string'],
                        ],
                        'entityId'  => [
                            'type'        => 'string',
                            'description' => 'ID of the entity record that provides context for condition evaluation and template rendering. Its fields are accessible as `{{ entity.* }}` in Twig expressions. Does not select which records the action operates on — use `where` for that.',
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
class EmailHandler extends AbstractActionTypeSyncHandler
{
}
