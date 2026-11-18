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

namespace Atro\SelectManagers;

use Atro\Core\SelectManagers\Base;
use Atro\Core\Utils\IdGenerator;

class Team extends Base
{
    /**
     * The teams of the current user.
     */
    protected function boolFilterOnlyMy(&$result): void
    {
        $uid = IdGenerator::unsortableId();

        $result['whereClause'][] = [
            'id' => [
                'innerSql' => [
                    'sql'        => "SELECT team_id FROM team_user WHERE deleted = :tm_del_{$uid} AND user_id = :tm_uid_{$uid}",
                    'parameters' => ["tm_del_{$uid}" => false, "tm_uid_{$uid}" => $this->getUser()->id],
                ],
            ],
        ];
    }

    /**
     * The access level "team" gives access to the teams of the user.
     */
    protected function accessOnlyTeam(&$result): void
    {
        $this->boolFilterOnlyMy($result);
    }
}
