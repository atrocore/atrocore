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
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\Templates\Repositories\Relation;
use Atro\Entities\FolderHierarchy as FolderHierarchyEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

        if ($entity->isNew()) {
            $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
                ->where(['folderId' => $entity->get('entityId')])
                ->findOne();
            if (!empty($fileFolderLinker)) {
                $this->updateItem($entity);
            } else {
                $this->createItem($entity);
            }
        } else {
            $this->updateItem($entity);
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

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->removeItem($entity);
    }

    public function getStorage(FolderHierarchyEntity $folderHierarchy): FileStorageInterface
    {
        $folder = $this->getEntityManager()->getRepository('Folder')->get($folderHierarchy->get('entityId'));
        $storage = $this->getEntityManager()->getRepository('Storage')->get($folder->get('storageId'));

        return $this->getInjection('container')->get($storage->get('type') . 'Storage');
    }

    public function createItem(Entity $entity): void
    {
        $folder = $this->getEntityManager()->getRepository('Folder')->get($entity->get('entityId'));

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')->get();
        $fileFolderLinker->set([
            'name'     => $folder->get('name'),
            'parentId' => $entity->get('parentId'),
            'folderId' => $entity->get('entityId')
        ]);

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function updateItem(Entity $entity): void
    {
        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['folderId' => $entity->get('entityId')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $fileFolderLinker->set('parentId', $entity->get('parentId'));

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function removeItem(Entity $entity): void
    {
        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['folderId' => $entity->get('entityId')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $fileFolderLinker->set('parentId', '');

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
    }
}