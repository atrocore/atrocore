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

use Atro\ORM\DB\MapperInterface;
use Espo\Services\RecordService;

class Archive extends Base
{
    protected const MAPPER_CLASS = '\ClickHouseIntegration\ORM\DB\ClickHouse\Mapper';

    protected bool $moveDataOnFind = true;

    public function hasClickHouse(): bool
    {
        return class_exists(self::MAPPER_CLASS) && !empty($this->getConfig()->get('clickhouse')['active']);
    }

    public function find(array $params = [])
    {
        if ($this->hasClickHouse() && $this->moveDataOnFind) {
            $this
                ->getInjection('container')
                ->get('\ClickHouseIntegration\Console\SyncEntity')
                ->moveData($this->entityName);
        }

        return parent::find($params);
    }

    public function hasDeletedRecordsToClear(): bool
    {
        if (empty($this->seed) || $this->hasClickHouse()) {
            return false;
        }

        return !empty($this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']));
    }

    public function clearDeletedRecords(): void
    {
        if (empty($this->seed) || $this->hasClickHouse()) {
            return;
        }

        $autoDeleteAfterDays = $this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']);

        if (!empty($autoDeleteAfterDays) && $autoDeleteAfterDays > 0) {
            $date = (new \DateTime())->modify("-$autoDeleteAfterDays days");

            $qb = $this->getConnection()->createQueryBuilder();
            $qb->delete($this->getConnection()->quoteIdentifier($this->getMapper()->toDb($this->entityName)));
            if ($this->seed->hasField('modifiedAt')) {
                $qb->where('modified_at < :date OR modified_at IS NULL');
            } elseif ($this->seed->hasField('createdAt')) {
                $qb->where('created_at < :date OR modified_at IS NULL');
            } else {
                return;
            }
            $qb->setParameter('date', $date->format('Y-m-d H:i:s'));
            $qb->executeQuery();
        }
    }

    public function getMapper(): MapperInterface
    {
        if (!$this->hasClickHouse()) {
            return parent::getMapper();
        }

        if (empty($this->mapper)) {
            $this->mapper = $this->getEntityManager()->getMapper(self::MAPPER_CLASS);
        }

        return $this->mapper;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getService(string $serviceName): RecordService
    {
        return $this->getInjection('container')->get('serviceFactory')->create($serviceName);
    }
}
