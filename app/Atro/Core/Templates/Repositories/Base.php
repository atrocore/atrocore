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
use Doctrine\DBAL\ParameterType;

class Base extends RDB
{
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

    public function getNumberOfRecordsToAutoDelete(): int
    {
        $autoDays = $this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']);
        if (empty($autoDays)) {
            return 0;
        }

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('count(*) as total')
            ->from($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        $date = new \DateTime();
        $date->modify("-{$autoDays} days");
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

        return (int)$qb->fetchOne();
    }

    public function clearDeletedRecords(?int $iteration = null, ?int $maxPerJob = null): void
    {
        if (empty($this->seed)) {
            return;
        }

        $autoDeleteAfterDays = $this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']);

        if (!empty($autoDeleteAfterDays) && $autoDeleteAfterDays > 0) {
            $date = (new \DateTime())->modify("-$autoDeleteAfterDays days");
            $limit = 2000;
            $count = 0;
            while (true) {
                $toDelete = [];
                $offset = empty($iteration) ? 0 : ($iteration - 1) * ($maxPerJob ?? 0);
                if ($this->seed->hasField('modifiedAt')) {
                    $toDelete = $this
                        ->where(['modifiedAt<' => $date->format('Y-m-d H:i:s')])
                        ->limit($offset, $limit)
                        ->order('id')
                        ->find();
                } elseif ($this->seed->hasField('createdAt')) {
                    $toDelete = $this
                        ->where(['createdAt<' => $date->format('Y-m-d H:i:s')])
                        ->limit($offset, $limit)
                        ->order('id')
                        ->find();
                }
                if (empty($toDelete[0])) {
                    break;
                }
                foreach ($toDelete as $entity) {
                    $this->getEntityManager()->removeEntity($entity);
                }

                $count += $limit;
                if (!empty($maxPerJob) && $count >= $maxPerJob) {
                    break;
                }
            }
        }
    }

    public function clearDeletedRecordsDefinitively(): void
    {
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
    }

}
