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

class InheritAllFromParentFactory extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['entityType']) || !isset($data['where'])) {
            return;
        }

        $exist = $this
            ->getEntityManager()
            ->getRepository('Job')
            ->select(['id'])
            ->where([
                'id!='   => $job->get('id'),
                'type'   => 'InheritAllFromParent',
                'status' => 'Running'
            ])
            ->findOne();

        if (!empty($exist)) {
            return;
        }

        $exist = $this
            ->getEntityManager()
            ->getRepository('Job')
            ->select(['id'])
            ->where([
                'type'   => ['InheritAllFromParentChunk'],
                'status' => ['Pending', 'Running']
            ])
            ->findOne();

        if (!empty($exist)) {
            return;
        }

        $service = $this->getServiceFactory()->create($data['entityType']);
        $repository = $this->getEntityManager()->getRepository($data['entityType']);

        $selectParams = $service->getSelectParams(['where' => $data['where']]);
        $selectParams['select'] = ['id'];

        $limit = $this->getConfig()->get('inheritAllFromParentChunkSize', 200);

        $offset = 0;
        $number = 1;

        while (true) {
            $records = $repository
                ->limit($offset, $limit)
                ->order('id', 'ASC')
                ->find($selectParams);

            if (empty($records[0])) {
                break;
            }

            $offset += $limit;

            $chunkJob = $this->getEntityManager()->getEntity('Job');
            $chunkJob->set([
                'name'     => sprintf($this->translate('inheritAllFromParentJobName'), $this->translate($data['entityType'], 'scopeNamesPlural'), $number),
                'type'     => 'InheritAllFromParentChunk',
                'payload'  => [
                    'entityType' => $data['entityType'],
                    'ids'        => array_column($records->toArray(), 'id')
                ],
                'priority' => 40,
            ]);
            $this->getEntityManager()->saveEntity($chunkJob);

            $number++;
        }
    }
}
