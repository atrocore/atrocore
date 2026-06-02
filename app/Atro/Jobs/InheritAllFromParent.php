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

class InheritAllFromParent extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['entityType']) || !isset($data['where'])) {
            return;
        }

        $service = $this->getServiceFactory()->create($data['entityType']);
        $repository = $this->getEntityManager()->getRepository($data['entityType']);

        $selectParams = $service->getSelectParams(['where' => $data['where']]);
        $selectParams['select'] = ['id'];

        $offset = 0;
        $limit = 2000;

        while (true) {
            $records = $repository
                ->limit($offset, $limit)
                ->order('id', 'ASC')
                ->find($selectParams);

            if (empty($records[0])) {
                break;
            }

            $offset += $limit;

            foreach ($records as $record) {
                try {
                    $service->inheritFromParent($record->get('id'));
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("Inherit from parent failed for {$data['entityType']} '{$record->get('id')}': {$e->getMessage()}");
                }
            }
        }
    }
}
