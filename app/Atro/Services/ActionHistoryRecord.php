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

namespace Atro\Services;

use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class ActionHistoryRecord extends Record
{
    protected $actionHistoryDisabled = true;

    protected $listCountQueryDisabled = true;

    protected $forceSelectAllAttributes = true;

    public function loadParentNameFields(Entity $entity)
    {
        if ($entity->get('targetId') && $entity->get('targetType')) {
            $targetType = $entity->get('targetType') === 'UserProfile' ? 'User' : $entity->get('targetType');
            $repository = $this->getEntityManager()->getRepository($targetType);
            if ($repository) {
                if ($repository instanceof ReferenceData) {
                    $target = $repository->get($entity->get('targetId'));
                } else {
                    $target = $repository->where(['id' => $entity->get('targetId')])->findOne(['withDeleted' => true]);
                }
                if ($target && $target->get('name')) {
                    $entity->set('targetName', $target->get('name'));
                }
            }
        }
    }
}
