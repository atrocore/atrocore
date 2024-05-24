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

use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;

class FolderHierarchy extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $folder = $this->getEntityManager()->getRepository('Folder')->get($entity->get('entityId'));
        if (!empty($folder)) {
            $folder->set('hash', Folder::createFolderHash($folder->get('name'), $entity->get('parentId')));
            try {
                $this->getEntityManager()->saveEntity($folder);
            } catch (UniqueConstraintViolationException $e) {
                throw new NotUnique($this->getInjection('language')->translate('suchFolderNameCannotBeUsed', 'exceptions', 'Folder'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $folder = $this->getEntityManager()->getRepository('Folder')->get($entity->get('entityId'));
        if (!empty($folder)) {
            $folder->set('hash', Folder::createFolderHash($folder->get('name'), null));
            try {
                $this->getEntityManager()->saveEntity($folder);
            } catch (UniqueConstraintViolationException $e) {
                throw new NotUnique($this->getInjection('language')->translate('suchFolderNameCannotBeUsed', 'exceptions', 'Folder'));
            }
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}