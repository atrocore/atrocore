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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;

class ClusterItem extends Base
{
    public function moveToCluster(string $clusterIdFrom, string $clusterIdTo): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('cluster_id=:clusterIdFrom')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->executeQuery();
    }
}
