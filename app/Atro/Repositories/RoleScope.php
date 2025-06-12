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

class RoleScope extends Base
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('hasAccess'))) {
            $entity->set('createAction', false);
            $entity->set('readAction', 'no');
            $entity->set('editAction', 'no');
            $entity->set('deleteAction', 'no');
            $entity->set('streamAction', 'no');
        }

        parent::beforeSave($entity, $options);
    }
}
