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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ClusterItem extends Base
{
    public function moveAllToCluster(string $clusterIdFrom, string $clusterIdTo): void
    {
        // check if 'From' cluster has no rejected items that are in 'To' cluster
        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('rejected_cluster_item', 'rci')
            ->join('rci', 'cluster_item', 'ci', 'ci.id=rejected_cluster_item.cluster_item_id')
            ->where('rci.cluster_id=:clusterIdFrom and ci.cluster_id= :clusterIdTo and rci.deleted = :false and ci.deleted = :false')
            ->andWhere('')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        if (!empty($res)){
            return;
        }

        $this->getConnection()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('cluster_id=:clusterIdFrom and id not in (select cluster_item_id from rejected_cluster_item where cluster_id=:clusterIdTo) and deleted=:false')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    public function moveToCluster(string $clusterItemId, string $clusterIdTo): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('id=:clusterItemId')
            ->setParameter('clusterItemId', $clusterItemId)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->executeQuery();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if (!empty($entity->get('matchedRecordId'))) {
            $this->getEntityManager()->getRepository('MatchedRecord')->markHasNoCluster($entity->get('matchedRecordId'));
        }
    }
}
