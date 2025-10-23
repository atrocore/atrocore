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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Archive;
use Atro\ORM\DB\MapperInterface;

class ActionHistoryRecord extends Archive
{
    public function getMapper(): MapperInterface
    {
        $className = '\ClickHouseIntegration\ORM\DB\ClickHouse\Mapper';
        if (!class_exists($className)) {
            return parent::getMapper();
        }

        if (empty($this->mapper)) {
            $this->mapper = $this->getEntityManager()->getMapper($className);
        }

        return $this->mapper;
    }
}
