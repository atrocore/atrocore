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

namespace Atro\SelectManagers;

use Atro\Core\SelectManagers\Base;

class User extends Base
{
    protected function access(&$result)
    {
        parent::access($result);

        if (!$this->getUser()->isAdmin()) {
            $result['whereClause'][] = ['isAdmin' => false];
        }
    }
}
