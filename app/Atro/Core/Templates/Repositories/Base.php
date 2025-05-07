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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Services\Record;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Base extends RDB
{
    public function duplicateAttributeValues(Entity $entity, Entity $duplicatingEntity): void
    {
        $tableName = Util::toUnderScore(lcfirst($entity->getEntityName()));

        $attrs = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('*')
            ->from("{$tableName}_attribute_value")
            ->where("deleted=:false")
            ->andWhere("{$tableName}_id=:id")
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $duplicatingEntity->get('id'))
            ->fetchAllAssociative();

        if (empty($attrs)) {
            return;
        }

        foreach ($attrs as $attr) {
            $attr['id'] = Util::generateId();
            $attr["{$tableName}_id"] = $entity->get('id');

            $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
            $qb->insert("{$tableName}_attribute_value");
            foreach ($attr as $column => $value) {
                $qb->setValue($column, ":{$column}");
                $qb->setParameter($column, $value, Mapper::getParameterType($value));
            }
            $qb->executeQuery();
        }
    }

    public function hasDeletedRecordsToClear(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        if (!empty($this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']))) {
            return true;
        }

        $clearDays = $this->getMetadata()->get(['scopes', $this->entityName, 'clearDeletedAfterDays']) ?? 60;

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        $date = new \DateTime();
        if ($clearDays > 0) {
            $date->modify("-{$clearDays} days");
        }
        $date = $date->format('Y-m-d H:i:s');

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', $date);
        } elseif ($this->seed->hasField('createdAt')) {
            $qb->andWhere('created_at<:date OR created_at IS NULL');
            $qb->setParameter('date', $date);
        }

        return !empty($qb->fetchOne());
    }

    public function clearDeletedRecords(): void
    {
        if (empty($this->seed)) {
            return;
        }

        $autoDeleteAfterDays = $this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']);

        if (!empty($autoDeleteAfterDays) && $autoDeleteAfterDays > 0) {
            $date = (new \DateTime())->modify("-$autoDeleteAfterDays days");

            // delete using massActions
            /** @var $service Record * */
            $service = $this->getEntityManager()->getContainer()->get('serviceFactory')->create($this->entityName);
            $where = [];

            if ($this->seed->hasField('modifiedAt')) {
                $where[] = [
                    'attribute' => 'modifiedAt',
                    'type'      => 'before',
                    'value'     => $date->format('Y-m-d H:i:s')
                ];
            } elseif ($this->seed->hasField('createdAt')) {
                $where[] = [
                    'attribute' => 'createdAt',
                    'type'      => 'before',
                    'value'     => $date->format('Y-m-d H:i:s')
                ];
            }

            if (!empty($where)) {
                $service->massRemove(['where' => $where]);
            }
        }

        $clearDays = $this->getMetadata()->get(['scopes', $this->entityName, 'clearDeletedAfterDays']) ?? 60;

        $date = new \DateTime();
        if ($clearDays > 0) {
            $date->modify("-{$clearDays} days");
        }
        $date = $date->format('Y-m-d H:i:s');

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->delete($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', $date);
        } elseif ($this->seed->hasField('createdAt')) {
            $qb->andWhere('created_at<:date OR created_at IS NULL');
            $qb->setParameter('date', $date);
        }

        $qb->executeQuery();

        if ($this->getMetadata()->get(['scopes', $this->entityName, 'hasAttribute'])) {
            $name = Util::toUnderScore(lcfirst($this->entityName));
            while (true) {
                $ids = $this->getConnection()->createQueryBuilder()
                    ->select('av.id')
                    ->from("{$name}_attribute_value", 'av')
                    ->leftJoin('av', $name, 'e', "e.id=av.{$name}_id")
                    ->leftJoin('av', $this->getConnection()->quoteIdentifier('attribute'), 'a', "a.id=av.attribute_id")
                    ->where('e.id IS NULL OR a.id IS NULL')
                    ->setFirstResult(0)
                    ->setMaxResults(20000)
                    ->fetchFirstColumn();
                if (empty($ids)) {
                    break;
                }
                $this->getConnection()->createQueryBuilder()
                    ->delete("{$name}_attribute_value")
                    ->where('ids IN (:ids)')
                    ->setParameter('ids', $ids, $this->getConnection()::PARAM_STR_ARRAY)
                    ->executeQuery();
            }
        }
    }
}
