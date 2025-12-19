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

class Classification extends Base
{
    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getBoolFilterParameter('onlyForEntity');
        if (!empty($entityName)) {
            if ($this->getMetadata()->get("scopes.$entityName.primaryEntityId")) {
                $entityName = $this->getMetadata()->get("scopes.$entityName.primaryEntityId");
            }
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }

    protected function boolFilterOnlyForChannel(array &$result): void
    {
        $channelId = (string)$this->getBoolFilterParameter('onlyForChannel');
        if (!empty($channelId)) {
            $result['whereClause'][] = [
                'channelId' => $channelId
            ];
        }
    }
}