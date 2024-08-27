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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\FileStorage\HasBasketInterface;
use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\Entities\Storage as StorageEntity;
use Atro\Entities\Folder as FolderEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    protected ?array $hierarchyData = null;

    public function getAllHierarchyData(): array
    {
        if ($this->hierarchyData === null) {
            $records = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('fh.parent_id, f1.name as parent_name, fh.entity_id, f.name as entity_name')
                ->from('folder_hierarchy', 'fh')
                ->innerJoin('fh', 'folder', 'f', 'f.id=fh.entity_id')
                ->innerJoin('fh', 'folder', 'f1', 'f1.id=fh.parent_id')
                ->where('fh.deleted=:false')
                ->andWhere('f.deleted=:false')
                ->andWhere('f1.deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $this->hierarchyData = [];
            foreach ($records as $record) {
                $this->hierarchyData[$record['entity_id']] = $record;
            }
        }

        return $this->hierarchyData;
    }

    public function getFolderHierarchyData(string $folderId): array
    {
        $hierarchyData = $this->getAllHierarchyData();

        $path = [];
        $id = $folderId;
        while (true) {
            if (!isset($hierarchyData[$id]) || $hierarchyData[$id]['parent_id'] === $hierarchyData[$id]['entity_id']) {
                break;
            }
            $path[] = [
                'id'         => $hierarchyData[$id]['entity_id'],
                'name'       => $hierarchyData[$id]['entity_name'],
                'parentId'   => $hierarchyData[$id]['parent_id'],
                'parentName' => $hierarchyData[$id]['parent_name'],
            ];
            $id = $hierarchyData[$id]['parent_id'];
        }

        return $path;
    }

    public function getRootStorage(): StorageEntity
    {
        $storage = $this->getEntityManager()->getRepository('Storage')->where(['folderId' => ''])->findOne();
        if (empty($storage)) {
            throw new Error("No Storage found.");
        }

        return $storage;
    }

    public function getFolderStorage(string $folderId): StorageEntity
    {
        if (empty($folderId)) {
            return $this->getRootStorage();
        }

        $folder = $this->get($folderId);
        if (empty($folder)) {
            throw new NotFound("Folder '{$folderId}' does not exist.");
        }

        $storage = $folder->get('storage');
        if (empty($storage)) {
            throw new NotFound("Storage '{$folder->get('storageId')}' does not exist.");
        }

        return $storage;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->isNew()) {
            $parent = $entity->getParent();
            if (empty($parent)) {
                $entity->set('storageId', $this->getRootStorage()->get('id'));
            } else {
                $entity->set('storageId', $parent->get('storageId'));
            }
            $this->createItem($entity);

            // create origin file
            if (empty($options['scanning']) && !$this->getStorage($entity)->createFolder($entity)) {
                throw new BadRequest($this->getInjection('language')->translate('folderCreateFailed', 'exceptions', 'Folder'));
            }
        } else {
            if ($entity->isAttributeChanged('name')) {
                $this->updateItem($entity);
                if (!$this->getStorage($entity)->renameFolder($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('folderRenameFailed', 'exceptions', 'File'));
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $storage = $this->getEntityManager()->getRepository('Storage')
            ->where(['folderId' => $entity->get('id')])
            ->findOne();

        if (!empty($storage)) {
            throw new BadRequest("Storage '{$storage->get('name')}' uses this folder.");
        }

        // delete all files inside folder
        $this->deleteFiles($entity);

        // delete children folders
        $this->deleteChildrenFolders($entity);

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->removeItem($entity);

        $storage = $this->getStorage($entity);
        if ($storage instanceof HasBasketInterface) {
            if (!$storage->deleteFolder($entity)) {
                throw new BadRequest($this->getInjection('language')->translate('folderDeleteFailed', 'exceptions', 'File'));
            }
        } else {
            $this->deleteFromDb($entity->get('id'));
        }
    }

    protected function afterRestore($entity)
    {
        $this->restoreItem($entity);
        $this->getStorage($entity)->restoreFolder($entity);
    }

    public function deleteFiles(FolderEntity $folder): void
    {
        while (true) {
            $files = $this->getEntityManager()->getRepository('File')
                ->where(['folderId' => $folder->get('id')])
                ->limit(0, 20000)
                ->find();

            if (empty($files[0])) {
                break;
            }
            foreach ($files as $file) {
                $this->getEntityManager()->removeEntity($file);
            }
        }
    }

    public function deleteChildrenFolders(FolderEntity $folder): void
    {
        $children = $folder->get('children');
        if (!empty($children[0])) {
            foreach ($children as $child) {
                $this->getEntityManager()->removeEntity($child);
            }
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        $inTransaction = false;

        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $res = parent::save($entity, $options);
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        if ($inTransaction) {
            $this->getPDO()->commit();
        }

        return $res;
    }

    public function remove(Entity $entity, array $options = [])
    {
        $inTransaction = false;

        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $res = parent::remove($entity, $options);
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        if ($inTransaction) {
            $this->getPDO()->commit();
        }

        return $res;
    }

    public function createItem(Entity $entity): void
    {
        if ($entity->getParentId() !== null) {
            return;
        }

        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')->get();
        $fileFolderLinker->set([
            'name'     => $entity->get('name'),
            'parentId' => '',
            'folderId' => $entity->get('id')
        ]);

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function updateItem(Entity $entity): void
    {
        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['folderId' => $entity->get('id')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $fileFolderLinker->set('name', $entity->get('name'));

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function deleteFromDb(string $id): bool
    {
        $this->deleteItemPermanently($id);

        return parent::deleteFromDb($id);
    }

    public function removeItem(Entity $entity): void
    {
        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['folderId' => $entity->get('id')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $this->getEntityManager()->removeEntity($fileFolderLinker);
    }

    public function restoreItem(Entity $entity): void
    {
        $storage = $entity->getStorage();
        if (empty($storage)) {
            return;
        }

        if ($storage->get('type') === 'local' && empty($storage->get('syncFolders'))) {
            return;
        }

        $folderHierarchy = $this->getEntityManager()->getRepository('FolderHierarchy')
            ->select(['parentId'])
            ->where(['entityId' => $entity->get('id')])
            ->findOne();

        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')->get();
        $fileFolderLinker->set([
            'name'     => $entity->get('name'),
            'parentId' => !empty($folderHierarchy) ? $folderHierarchy->get('parentId') : '',
            'folderId' => $entity->get('id')
        ]);

        try {
            $this->getEntityManager()->saveEntity($fileFolderLinker);
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function deleteItemPermanently(string $folderId): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('file_folder_linker')
            ->where('folder_id = :folderId')
            ->andWhere('file_id IS NULL')
            ->setParameter('folderId', $folderId)
            ->executeQuery();
    }

    public function getStorage(FolderEntity $folder): FileStorageInterface
    {
        return $this->getEntityManager()->getRepository('Storage')->getFileStorage($folder->get('storageId'));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
