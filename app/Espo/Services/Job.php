<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Services;


use Atro\ORM\DB\RDB\Mapper;

class Job extends Record
{
    protected $forceSelectAllAttributes = true;

    public function deleteOld(): bool
    {
        $statuses = ['Success', 'Failed'];
        $days = $this->getConfig()->get('jobsMaxDays', 21);
        if ($days === 0) {
            return true;
        }

        // delete
        while (true) {
            $toDelete = $this->getEntityManager()->getRepository('Job')
                ->where([
                    'createdAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s'),
                    'status' => $statuses
                ])
                ->limit(0, 2000)
                ->order('createdAt')
                ->find();
            if (empty($toDelete[0])) {
                break;
            }
            foreach ($toDelete as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete forever
        $daysToDeleteForever = $days + 14;
        $connection = $this->getEntityManager()->getConnection();
        $connection->createQueryBuilder()
            ->delete('job')
            ->where('execute_time < :executeTime')
            ->andWhere('status IN (:statuses)')
            ->setParameter('executeTime', (new \DateTime())->modify("-$daysToDeleteForever days")->format('Y-m-d H:i:s'))
            ->setParameter('statuses', $statuses, Mapper::getParameterType($statuses))
            ->executeStatement();

        return true;
    }
}
