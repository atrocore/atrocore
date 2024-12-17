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

namespace Atro\Services;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Cron\CronExpression;
use Espo\ORM\Entity;

class ScheduledJob extends Base
{
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->isScheduledValid($entity);

        parent::beforeCreateEntity($entity, $data);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->isScheduledValid($entity);

        parent::beforeUpdateEntity($entity, $data);
    }

    protected function isScheduledValid(Entity $entity): bool
    {
        if (!empty($entity->get('scheduling'))) {
            try {
                $cronExpression = CronExpression::factory($entity->get('scheduling'));
            } catch (\Exception $e) {
                // prepare key
                $key = 'wrongCrontabConfiguration';

                // prepare message
                $message = $this
                    ->getInjection('language')
                    ->translate($key, 'exceptions', 'ScheduledJob');

                throw new Error($message);
            }
        }

        return true;
    }

    public function executeNow(string $id): bool
    {
        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'           => $entity->get('name'),
            'status'         => 'Pending',
            'scheduledJobId' => $entity->get('id'),
            'type'           => $entity->get('type'),
            'priority'       => 200
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
