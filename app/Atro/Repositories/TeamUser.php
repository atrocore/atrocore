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

use Atro\Core\Templates\Repositories\Relation;
use Espo\ORM\Entity;

/**
 * The membership of a user in a team: changing it changes the access rights of the user.
 */
class TeamUser extends Relation
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->afterMembershipChanged();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->afterMembershipChanged();
    }

    private function afterMembershipChanged(): void
    {
        $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
        $this->getInjection('aclManager')->clearAclCache();
    }
}
