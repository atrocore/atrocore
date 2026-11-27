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
    path: '/ClusterItem/{id}/unmerge',
    methods: [
        'POST',
    ],
    summary: 'Split a single cluster item (deprecated)',
    description: 'Deprecated, use POST /ClusterItem/{id}/split instead. Moves the specified cluster item to a new cluster. If the item is consolidated, it is deconsolidated first. The item may not be the master entity item of its cluster.',
    tag: 'ClusterItem',
    operationId: 'unmergeClusterItem',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the ClusterItem to split.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the item was moved to a new cluster.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The item is the master entity item and cannot be split.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'ClusterItem not found.',
        ],
    ],
    deprecated: true,
)]
class UnmergeHandler extends SplitHandler
{
}
