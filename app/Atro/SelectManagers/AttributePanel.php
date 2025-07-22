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

use Espo\Core\SelectManagers\Base;

class AttributePanel extends Base
{
    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getBoolFilterParameter('onlyForEntity');
        if (!empty($entityName)) {
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }
}
