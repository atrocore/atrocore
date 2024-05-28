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

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\Entities\Storage as StorageEntity;
use Atro\Entities\Folder as FolderEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    public function getFolderStorage(string $folderId, bool $fromDbOnly = false): StorageEntity
    {
        while (true) {
            $folder = $this->get($folderId);
            if (empty($folder)) {
                throw new NotFound();
            }

            $storage = $this->getEntityManager()->getRepository('Storage')
                ->where(['folderId' => $folderId])
                ->findOne();
            if (!empty($storage)) {
                return $storage;
            }

            $parent = $folder->getParent($fromDbOnly);
            if (empty($parent)) {
                $storage = $this->getEntityManager()->getRepository('Storage')
                    ->where(['folderId' => ''])
                    ->findOne();
                if (!empty($storage)) {
                    return $storage;
                }
                break;
            }

            $folderId = $parent->get('id');
        }

        throw new Error("No Storage found.");
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->isNew()) {
            $parent = $entity->getParent();
            if (empty($parent)) {
                $entity->set('storageId', $this->getFolderStorage('')->get('id'));
            } else {
                $entity->set('storageId', $parent->get('storageId'));
            }
            $this->createItem($entity);
        } else {
            if ($entity->isAttributeChanged('name')) {
                $this->updateItem($entity);
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
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
        $inTransaction = $this->getPDO()->inTransaction();

        if (!$inTransaction) {
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
        $inTransaction = $this->getPDO()->inTransaction();

        if (!$inTransaction) {
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
        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')->get();
        $fileFolderLinker->set([
            'name'     => $entity->get('name'),
            'parentId' => $entity->get('parentsIds')[0] ?? '',
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

    public function removeItem(Entity $entity): void
    {
        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
            ->where(['folderId' => $entity->get('id')])
            ->findOne();

        if (empty($fileFolderLinker)) {
            return;
        }

        $this->getEntityManager()->removeEntity($fileFolderLinker);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
