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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\ORM\IEntity;

class ClusterItem extends Base
{
    public function _getRecord(): ?IEntity
    {
        if (empty($this->get('entityId'))) {
            return null;
        }

        if (!isset($this->relationsContainer['record'])) {
            $this->setRelationValue('record', $this->getEntityManager()->getEntity($this->get('entityName'), $this->get('entityId')));
        }

        return $this->relationsContainer['record'];
    }
}
