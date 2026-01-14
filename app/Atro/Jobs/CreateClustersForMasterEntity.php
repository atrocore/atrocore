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

namespace Atro\Jobs;

use Atro\Entities\Job;
use Atro\Repositories\MatchedRecord;
use Espo\ORM\Entity;

class CreateClustersForMasterEntity extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        if (empty($masterEntity)) {
            return;
        }

        while (!empty($items = $this->getMatchedRecordRepository()->getForMasterEntity($masterEntity, 5))) {
            $clustersIds = [];

            foreach ($items as $item) {
                if (!empty($item['source_cluster_id'])) {
                    $clustersIds[$item['source_entity']][$item['source_entity_id']] = $item['source_cluster_id'];
                }
                if (!empty($item['master_cluster_id'])) {
                    $clustersIds[$item['master_entity']][$item['master_entity_id']] = $item['master_cluster_id'];
                }

                $sourceClusterId = $clustersIds[$item['source_entity']][$item['source_entity_id']] ?? null;
                $masterClusterId = $clustersIds[$item['master_entity']][$item['master_entity_id']] ?? null;

                if (!empty($sourceClusterId) && !empty($masterClusterId)) {
                    // move cluster items to one cluster
                    echo '<pre>';
                    print_r('123');
                    die();
                }

                $clusterId = $masterClusterId ?? $sourceClusterId ?? $this->createCluster($masterEntity)->id;
            }
        }

        echo '<pre>';
        print_r('123');
        die();

        // $m1 -> $m2;                                                  // $m1, $m2, $s1, $s2, $s3

        // $s1 -> $m1

        // $s3 -> $s2

        // $s2 -> $s1

        // $s2 -> $m1


        // 1. $m1, $m2       | CLUSTER_ID: 10
        // 2. $m1, $m2, $s1  | CLUSTER_ID: 10

        // 3. $s3, $s2       | CLUSTER_ID: 22

        // 4. $m1, $m2, $s1, $s2, $s3 | CLUSTER_ID: 10 (CLUSTER_ID: 22 -> removed)


//        while (true) {
////            $matchedRecords = $this->getEntityManager()->getRepository('MatchedRecord')
////                ->where(['clusterItemId' => null])
////                ->limit(0, 5000)
////                ->find();
//
//
//        }


    }

    protected function createCluster(string $masterEntity): Entity
    {
        $cluster = $this->getEntityManager()->getRepository('Cluster')->get();
        $cluster->set('masterEntity', $masterEntity);

        $this->getEntityManager()->saveEntity($cluster);

        return $cluster;
    }

    protected function getMatchedRecordRepository(): MatchedRecord
    {
        return $this->getEntityManager()->getRepository('MatchedRecord');
    }
}
