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

use Atro\Core\Utils\Util;
use Atro\ORM\DB\MapperInterface;

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

    public function count(array $params = [])
    {
        if ($this->hasClickHouse() && $this->moveDataOnFind) {
            $this
                ->getInjection('container')
                ->get('\ClickHouseIntegration\Console\SyncEntity')
                ->moveData($this->entityName);
        }

        return parent::count($params);
    }

    public function hasDeletedRecordsToClear(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        return !empty($this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays'])) || $this->hasClickHouse();
    }

    public function clearDeletedRecords(): void
    {
        if (empty($this->seed)) {
            return;
        }

        if ($this->hasClickHouse()) {
            $this
                ->getInjection('container')
                ->get('\ClickHouseIntegration\Console\ClearEntity')
                ->clearData($this->entityName);

            return;
        }

        $autoDeleteAfterDays = (int)$this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']);
        if (empty($autoDeleteAfterDays) || $autoDeleteAfterDays < 1) {
            return;
        }

        $this->getConnection()->createQueryBuilder()
            ->delete(Util::toUnderScore(lcfirst($this->entityName)))
            ->where('created_at < :date')
            ->setParameter('date', (new \DateTime())->modify("-$autoDeleteAfterDays days")->format('Y-m-d H:i:s'))
            ->executeQuery();
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
}
