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
use Atro\Services\Record;

class Archive extends Base
{
    public function hasDeletedRecordsToClear(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        if (!empty($this->getMetadata()->get(['scopes', $this->entityName, 'autoDeleteAfterDays']))) {
            return true;
        }

        return parent::hasDeletedRecordsToClear();
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
            /** @var Record $service */
            $service = $this->getEntityManager()->getContainer()->get('serviceFactory')->create($this->entityName);
            $where = [];

            if ($this->seed->hasField('modifiedAt')) {
                $where[] = [
                    'attribute' => 'modifiedAt',
                    'type'      => 'before',
                    'value'     => $date->format('Y-m-d H:i:s'),
                ];
            } elseif ($this->seed->hasField('createdAt')) {
                $where[] = [
                    'attribute' => 'createdAt',
                    'type'      => 'before',
                    'value'     => $date->format('Y-m-d H:i:s'),
                ];
            }

            if (!empty($where)) {
                $service->massRemove(['where' => $where]);
            }
        }

        parent::clearDeletedRecords();
    }

    public function getMapper(): MapperInterface
    {
        $className = '\ClickhouseIntegration\ORM\DB\ClickHouse\Mapper';
        if (!class_exists($className)) {
            return parent::getMapper();
        }

        if (empty($this->mapper)) {
            $this->mapper = $this->getEntityManager()->getMapper($className);
        }

        return $this->mapper;
    }
}
