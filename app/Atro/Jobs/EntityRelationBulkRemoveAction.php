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

class EntityRelationBulkRemoveAction extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['entityType']) || empty($data['link']) || empty($data['ids']) || empty($data['foreignIds'])) {
            return;
        }

        /** @var \Atro\Services\MassActions $service */
        $service = $this->getServiceFactory()->create('MassActions');
        $result  = $service->removeRelation($data['ids'], $data['foreignIds'], $data['entityType'], $data['link'], $data['relationData'] ?? null);

        if (!empty($result['message'])) {
            $job->set('message', $result['message']);
            $this->getEntityManager()->saveEntity($job);
        }
    }
}
