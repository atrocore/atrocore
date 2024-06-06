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

use Atro\Core\Exceptions\NotModified;
use Doctrine\DBAL\ParameterType;
use Atro\Core\EventManager\Event;
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
        $daysToDeleteForever = $days + $this->getConfig()->get('queueItemsDeletedMaxDays', 14);;
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
