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

use Espo\Core\SelectManagers\Base;

class Unit extends Base
{
    protected function boolFilterNotEntity(array &$result)
    {
        if (!empty($id = $this->getBoolFilterParameter('notEntity'))) {
            $result['whereClause'][] = [
                'id!=' => $id
            ];
        }
    }

    protected function boolFilterFromMeasure(array &$result)
    {
        $data = $this->getBoolFilterParameter('fromMeasure');
        if (!empty($data['measureId'])) {
            $result['whereClause'][] = [
                'measureId' => $data['measureId'],
                'isActive'  => true
            ];
        }
    }

    protected function boolFilterNotConverted(array &$result)
    {
        $result['whereClause'][] = [
            'convertToId' => null
        ];
    }
}