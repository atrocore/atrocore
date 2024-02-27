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

use Atro\Core\Exceptions\NotModified;
use Doctrine\DBAL\ParameterType;
use Espo\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Services\Base;

class QueueItem extends Base
{
    public function deleteOld(): bool
    {
        $days = $this->getConfig()->get('queueItemsMaxDays', 21);
        if ($days === 0) {
            return true;
        }

        // delete
        while (true) {
            $toDelete = $this->getEntityManager()->getRepository('QueueItem')
                ->where(['modifiedAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s')])
                ->limit(0, 2000)
                ->order('modifiedAt')
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

    public function massCancel(\stdClass $data): bool
    {
        if (property_exists($data, 'ids')) {
            $collection = $this->getRepository()->where(['id' => $data->ids, 'status!=' => 'Canceled'])->find();
        } elseif (property_exists($data, 'where')) {
            $where = json_decode(json_encode($data->where), true);
            $where[] = [
                'type'      => 'notIn',
                'attribute' => 'status',
                'value'     => [
                    'Canceled'
                ],
            ];

            $selectParams = $this->getSelectParams(['where' => $where]);
            $this->getRepository()->handleSelectParams($selectParams);
            $collection = $this->getRepository()->find(array_merge($selectParams));
        } else {
            return false;
        }

        foreach ($collection as $entity) {
            if (!$this->getAcl()->check($entity, 'edit')) {
                continue;
            }
            $entity->set('status', 'Canceled');
            try {
                $this->getEntityManager()->saveEntity($entity);
            } catch (BadRequest $e) {
            } catch (NotModified $e) {
            } catch (Forbidden $e) {
            }
        }

        return true;
    }

    public function massRemove(array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassRemove', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $collection = $this->getRepository()->where(['id' => $params['ids']])->find();
        } elseif (array_key_exists('where', $params)) {
            $selectParams = $this->getSelectParams(['where' => $params['where']]);
            $this->getRepository()->handleSelectParams($selectParams);
            $collection = $this->getRepository()->find(array_merge($selectParams));
        } else {
            return false;
        }

        foreach ($collection as $entity) {
            if (!$this->getAcl()->check($entity, 'delete')) {
                continue;
            }
            $this->getEntityManager()->removeEntity($entity);
        }

        return true;
    }
}
