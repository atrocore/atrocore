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
    protected int $cleanupDays = 60;

    public function hasDeletedRecordsToCleanup(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->getConnection()->quoteIdentifier($this->getEntityManager()->getMapper()->toDb($this->entityName)))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        $date = (new \DateTime())->modify("-{$this->cleanupDays} days")->format('Y-m-d H:i:s');

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

        $qb = $this->getConnection()->createQueryBuilder()
            ->delete($this->getConnection()->quoteIdentifier($this->getEntityManager()->getMapper()->toDb($this->entityName)))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', (new \DateTime())->modify("-{$this->cleanupDays} days")->format('Y-m-d H:i:s'));
        }

        $qb->executeQuery();
    }
}
