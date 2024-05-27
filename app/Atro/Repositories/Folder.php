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
use Espo\Core\Utils\Util;
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

        parent::beforeSave($entity, $options);
    }

    protected function insertEntity(Entity $entity, bool $ignoreDuplicate): bool
    {
        $inTransaction = $this->getPDO()->inTransaction();

        if (!$inTransaction) {
            $this->getPDO()->beginTransaction();
        }

        try {
            $res = parent::insertEntity($entity, $ignoreDuplicate);
            if ($res) {
                $this->createItem($entity);
            }
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

    protected function updateEntity(Entity $entity): bool
    {
        $inTransaction = $this->getPDO()->inTransaction();

        if (!$inTransaction) {
            $this->getPDO()->beginTransaction();
        }

        try {
            $res = parent::updateEntity($entity);
            if ($res && $entity->isAttributeChanged('name')) {
                $this->updateItem($entity);
            }
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

    protected function deleteEntity(Entity $entity): bool
    {
        $inTransaction = $this->getPDO()->inTransaction();

        if (!$inTransaction) {
            $this->getPDO()->beginTransaction();
        }

        try {
            $res = parent::deleteEntity($entity);
            if ($res) {
                $this->removeItem($entity);
            }
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

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        // delete all files inside folder
        $this->deleteFiles($entity);

        // delete children folders
        $this->deleteChildrenFolders($entity);

        parent::beforeRemove($entity, $options);
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

    public function createItem(Entity $entity): void
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->insert('file_folder_linker')
            ->setValue('id', ':id')
            ->setValue('name', ':name')
            ->setValue('parent_id', ':parentId')
            ->setValue('folder_id', ':folderId')
            ->setParameter('id', Util::generateId())
            ->setParameter('name', $entity->get('name'))
            ->setParameter('parentId', '')
            ->setParameter('folderId', $entity->get('id'));
        try {
            $qb->executeQuery();
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function updateItem(Entity $entity): void
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->update('file_folder_linker')
            ->set('name', ':name')
            ->where('folder_id=:folderId')
            ->setParameter('name', $entity->get('name'))
            ->setParameter('folderId', $entity->get('id'));
        try {
            $qb->executeQuery();
        } catch (UniqueConstraintViolationException $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
        }
    }

    public function removeItem(Entity $entity): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('file_folder_linker')
            ->where('folder_id=:folderId')
            ->setParameter('folderId', $entity->get('id'))
            ->executeQuery();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
