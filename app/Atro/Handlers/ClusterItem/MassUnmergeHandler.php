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

namespace Atro\Handlers\ClusterItem;

use Atro\Core\Routing\Route;

#[Route(
    path: '/ClusterItem/massUnmerge',
    methods: [
        'POST',
    ],
    summary: 'Split cluster items (mass action) (deprecated)',
    description: 'Deprecated, use POST /ClusterItem/massSplit instead. Moves one or more cluster items together to a new cluster. Consolidated items are deconsolidated first. All selected items must belong to the same cluster and none may be the master entity item. Accepts a list of IDs via idList or a filter via where.',
    tag: 'ClusterItem',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'idList' => [
                            'type'        => 'array',
                            'description' => 'List of ClusterItem IDs to split.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'where'  => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting ClusterItems to split.',
                            'items'       => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                    'anyOf'      => [
                        ['required' => ['idList']],
                        ['required' => ['where']],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Split result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'  => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items moved to the new cluster.',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'Always true — split is executed synchronously.',
                            ],
                            'errors' => [
                                'type'        => 'array',
                                'items'       => [
                                    'type' => 'string',
                                ],
                                'description' => 'List of error messages, if any.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Neither idList nor where was provided; items belong to different clusters; or a master entity item was selected.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
    ],
    deprecated: true,
)]
class MassUnmergeHandler extends MassSplitHandler
{
}
