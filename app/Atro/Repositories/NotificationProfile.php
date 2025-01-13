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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\DataManager;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class NotificationProfile extends Base
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (($entity->isNew() && $entity->get('isActive')) || $entity->isAttributeChanged('isActive')) {
            $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
        }

    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('isActive')) {
            $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
        }

        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        if ($entity->get('isActive')) {
            $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
        }

        parent::afterRestore($entity);
    }
}
