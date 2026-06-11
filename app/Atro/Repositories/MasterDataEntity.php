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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class MasterDataEntity extends Base
{
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $pipelines = $this->getEntityManager()
            ->getRepository('SourceToStagingPipeline')
            ->where(['stagingEntityId' => $entity->get('id')])
            ->find();

        foreach ($pipelines as $pipeline) {
            $this->getEntityManager()->removeEntity($pipeline);
        }
    }
}