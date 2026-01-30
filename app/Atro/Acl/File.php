<?php

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