<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Espo\Jobs;

use Doctrine\DBAL\ParameterType;
use Espo\Core\Jobs\Base;

class DeleteQueueItems extends Base
{
    public function run(): bool
    {
        // delete
        $days = $this->getConfig()->get('queueItemsMaxDays', 21);
        $toDelete = $this->getEntityManager()->getRepository('QueueItem')
            ->where(['modifiedAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s')])
            ->limit(0, 2000)
            ->order('modifiedAt')
            ->find();
        foreach ($toDelete as $entity) {
            $this->getEntityManager()->removeEntity($entity);
        }

        // delete forever
        $daysToDeleteForever = $days + 14;
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('queue_item')
            ->where('modified_at < :maxDate')
            ->andWhere('deleted = :true')
            ->setParameter('maxDate', (new \DateTime())->modify("-$daysToDeleteForever days")->format('Y-m-d H:i:s'))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();

        return true;
    }
}
