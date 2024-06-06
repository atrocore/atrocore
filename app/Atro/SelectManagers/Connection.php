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

use Atro\ConnectionType\HttpConnectionInterface;
use Espo\Core\SelectManagers\Base;

class Connection extends Base
{
    protected function boolFilterNotEntity(array &$result)
    {
        if (!empty($id = $this->getBoolFilterParameter('notEntity'))) {
            $result['whereClause'][] = [
                'id!=' => $id
            ];
        }
    }

    protected function boolFilterConnectionType(array &$result)
    {
        if (!empty($type = $this->getBoolFilterParameter('connectionType'))) {
            $result['whereClause'][] = [
                'type' => $type
            ];
        }
    }

    protected function boolFilterHttpConnection(array &$result)
    {
        $types = [];
        foreach ($this->getMetadata()->get(['app', 'connectionTypes'], []) as $type => $className) {
            if (is_a($className, HttpConnectionInterface::class, true)) {
                $types[] = $type;
            }
        }

        $result['whereClause'][] = [
            'type' => $types
        ];
    }
}
