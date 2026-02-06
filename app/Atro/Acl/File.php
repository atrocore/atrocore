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

namespace Atro\Acl;

use Espo\Core\Acl\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class File extends Base
{
    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!empty($entity->get('exportJobId'))) {
            $exportJob = $this->getEntityManager()->getEntity('ExportJob', $entity->get('exportJobId'));

            if (!empty($exportJob)) {
                if (!$this->getAclManager()->checkEntity($user, $exportJob, 'read')) {
                    return false;
                }
            }
        }

        if (!empty($entity->get('importJobId'))) {
            $importJob = $this->getEntityManager()->getEntity('ImportJob', $entity->get('importJobId'));

            if (!empty($importJob)) {
                if (!$this->getAclManager()->checkEntity($user, $importJob, 'read')) {
                    return false;
                }
            }
        }

        return parent::checkEntity($user, $entity, $data, 'read');
    }
}