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

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Cluster extends Base
{
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('ClusterItem')
            ->where(['clusterId' => $entity->get('id')])
            ->removeCollection();
    }
}
