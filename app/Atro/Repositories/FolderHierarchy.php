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
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\Templates\Repositories\Relation;
use Atro\Entities\FolderHierarchy as FolderHierarchyEntity;
use Espo\ORM\Entity;

class FolderHierarchy extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $parentStorage = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('parentId'));
        $entityStorage = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('entityId'));

        if ($parentStorage->get('id') !== $entityStorage->get('id')) {
            throw new BadRequest($this->getInjection('language')->translate('itemCannotBeMovedToAnotherStorage', 'exceptions', 'Storage'));
        }

        parent::beforeSave($entity, $options);

        if (!$entity->isNew() && $entity->isAttributeChanged('parentId')) {
            if (!$this->getStorage($entity)->moveFolder($entity->get('entityId'), $entity->getFetched('parentId'), $entity->get('parentId'))) {
                throw new BadRequest($this->getInjection('language')->translate('folderMoveFailed', 'exceptions', 'Folder'));
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (empty($options['ignoreValidation'])) {
            $parentStorage = $this->getEntityManager()->getRepository('Folder')->getRootStorage();
            $entityStorage = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('entityId'));

            if ($parentStorage->get('id') !== $entityStorage->get('id')) {
                throw new BadRequest($this->getInjection('language')->translate('itemCannotBeMovedToAnotherStorage', 'exceptions', 'Storage'));
            }
        }

        parent::beforeRemove($entity, $options);

        if (!empty($options['move'])) {
            if (!$this->getStorage($entity)->moveFolder($entity->get('entityId'), $entity->get('parentId'), '')) {
                throw new BadRequest($this->getInjection('language')->translate('folderMoveFailed', 'exceptions', 'Folder'));
            }
        }
    }

    public function getStorage(FolderHierarchyEntity $folderHierarchy): FileStorageInterface
    {
        $folder = $this->getEntityManager()->getRepository('Folder')->get($folderHierarchy->get('entityId'));
        $storage = $this->getEntityManager()->getRepository('Storage')->get($folder->get('storageId'));

        return $this->getInjection('container')->get($storage->get('type') . 'Storage');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
    }
}