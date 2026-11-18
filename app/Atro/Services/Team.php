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

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;

class Team extends Base
{
    protected $linkSelectParams = [
        'users' => [
            'additionalColumns' => [
                'role' => 'teamRole',
            ],
        ],
    ];

    /**
     * Returns the IDs of the users of the teams. Lets a client resolve the membership without read access
     * to the TeamUser relation, which has no ACL of its own.
     */
    public function getUserIds(array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $userIds = [];
        foreach ($this->getEntityManager()->getRepository('TeamUser')->where(['teamId' => $teamIds])->find() as $teamUser) {
            $userIds[] = $teamUser->get('userId');
        }

        return array_values(array_unique($userIds));
    }
}
