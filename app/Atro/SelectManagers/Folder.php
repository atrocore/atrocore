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

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;

class Folder extends Base
{
    protected bool $hasOnlyHiddenFilter = false;

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        if (!$this->hasOnlyHiddenFilter) {
            $result['whereClause'][] = [
                'hidden' => false
            ];
        }
    }

    protected function boolFilterOnlyHidden(&$result)
    {
        $this->hasOnlyHiddenFilter = true;

        $result['whereClause'][] = [
            'hidden' => true
        ];
    }
}
