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
    public function hasDeletedRecordsToCleanup(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        if (!empty($this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']))) {
            return true;
        }

        $cleanDays = $this->getMetadata()->get(['scopes', $this->entityName, 'cleanDeletedAfterDays']) ?? 60;

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        $date = new \DateTime();
        if ($cleanDays > 0) {
            $date->modify("-{$cleanDays} days");
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

        return !empty($qb->fetchAssociative());
    }

    public function cleanupDeletedRecords(): void
    {
        if (empty($this->seed)) {
            return;
        }

        $autoDeleteAfterDays = $this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']);

        if (!empty($autoDeleteAfterDays) && $autoDeleteAfterDays > 0) {
            $date = (new \DateTime())->modify("-$autoDeleteAfterDays days");
            while (true) {
                $toDelete = [];
                if ($this->seed->hasField('modifiedAt')) {
                    $toDelete = $this
                        ->where(['modifiedAt<' => $date->format('Y-m-d H:i:s')])
                        ->limit(0, 2000)
                        ->order('modifiedAt')
                        ->find();
                } elseif ($this->seed->hasField('createdAt')) {
                    $toDelete = $this
                        ->where(['createdAt<' => $date->format('Y-m-d H:i:s')])
                        ->limit(0, 2000)
                        ->order('createdAt')
                        ->find();
                }
                if (empty($toDelete[0])) {
                    break;
                }
                foreach ($toDelete as $entity) {
                    $this->getEntityManager()->removeEntity($entity);
                }
            }
        }

        $cleanDays = $this->getMetadata()->get(['scopes', $this->entityName, 'cleanDeletedAfterDays']) ?? 60;

        $date = new \DateTime();
        if ($cleanDays > 0) {
            $date->modify("-{$cleanDays} days");
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
