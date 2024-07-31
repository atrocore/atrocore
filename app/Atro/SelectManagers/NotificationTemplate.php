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

class NotificationTemplate extends Base
{
    protected function boolFilterTransportType(array &$result)
    {
        if (!empty($type = $this->getBoolFilterParameter('transportType'))) {
            $result['whereClause'][] = [
                'type' => $type
            ];
        }
    }
}
