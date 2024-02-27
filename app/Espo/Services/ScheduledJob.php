<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Services;

use Cron\CronExpression;
use Doctrine\DBAL\ParameterType;
use Espo\Core\CronManager;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;

class ScheduledJob extends \Espo\Core\Templates\Services\Base
{
    /**
     * @param Entity $entity
     * @param        $data
     *
     * @throws Error
     */
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->isScheduledValid($entity);

        parent::beforeCreateEntity($entity, $data);
    }

    /**
     * @param Entity $entity
     * @param        $data
     *
     * @throws Error
     */
    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->isScheduledValid($entity);

        parent::beforeUpdateEntity($entity, $data);
    }

    /**
     * Is scheduled valid
     *
     * @param Entity $entity
     *
     * @return bool
     */
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

    public function executeNow(string $id)
    {
        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        $start = date("Y-m-d H:i:00");
        $end = date("Y-m-d H:i:59");
        $result = $this->getRepository()->getConnection()->createQueryBuilder()
            ->select(['id'])
            ->from('job')
            ->where('deleted = :deleted')
            ->andWhere('scheduled_job_id = :scheduledJobId')
            ->andWhere("execute_time >= :start")
            ->andWhere("execute_time <= :end")
            ->setParameter('scheduledJobId', $id)
            ->setParameter('deleted', false, ParameterType::BOOLEAN)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->fetchAllAssociative();

        if (!empty($result)) {
            return false;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set(array(
            'name'           => $entity->get('name'),
            'status'         => CronManager::PENDING,
            'scheduledJobId' => $entity->id,
            'executeTime'    => date("Y-m-d H:i:s")
        ));
        $this->getEntityManager()->saveEntity($jobEntity);

        return true;
    }

    /**
     * Init
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
