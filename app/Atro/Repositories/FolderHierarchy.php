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
use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;

class FolderHierarchy extends Relation
{
//    protected function beforeSave(Entity $entity, array $options = [])
//    {
////        $folderStorageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('entityId') ?? '', true)->get('id');
////        $parentFolderStorageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('parentId') ?? '', true)->get('id');
////
////        if ($folderStorageId !== $parentFolderStorageId) {
////            throw new BadRequest($this->getInjection('language')->translate('fileCannotBeMovedToAnotherStorage', 'exceptions', 'File'));
////        }
//
//        $this->updateItem($entity);
//
//        parent::beforeSave($entity, $options);
//    }
//
//    public function save(Entity $entity, array $options = [])
//    {
//        $inTransaction = $this->getPDO()->inTransaction();
//
//        if (!$inTransaction) {
//            $this->getPDO()->beginTransaction();
//            $inTransaction = true;
//        }
//
//        try {
//            $res = parent::save($entity, $options);
//        } catch (\Throwable $e) {
//            if ($inTransaction) {
//                $this->getPDO()->rollBack();
//            }
//            throw $e;
//        }
//
//        if ($inTransaction) {
//            $this->getPDO()->commit();
//        }
//
//        return $res;
//    }
//
//    protected function afterRemove(Entity $entity, array $options = [])
//    {
//        parent::afterRemove($entity, $options);
//
//        $this->removeItem($entity);
//    }
//
//    public function remove(Entity $entity, array $options = [])
//    {
//        $inTransaction = $this->getPDO()->inTransaction();
//
//        if (!$inTransaction) {
//            $this->getPDO()->beginTransaction();
//            $inTransaction = true;
//        }
//
//        try {
//            $res = parent::remove($entity, $options);
//        } catch (\Throwable $e) {
//            if ($inTransaction) {
//                $this->getPDO()->rollBack();
//            }
//            throw $e;
//        }
//
//        if ($inTransaction) {
//            $this->getPDO()->commit();
//        }
//
//        return $res;
//    }
//
//    public function updateItem(Entity $entity): void
//    {
//        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
//            ->where(['folderId' => $entity->get('entityId')])
//            ->findOne();
//
//        if (empty($fileFolderLinker)) {
//            $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')->get();
//            $fileFolderLinker->set('folderId', $entity->get('entityId'));
//        }
//
//        $fileFolderLinker->set('parentId', $entity->get('parentId'));
//
//        try {
//            $this->getEntityManager()->saveEntity($fileFolderLinker);
//        } catch (UniqueConstraintViolationException $e) {
//            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
//        }
//    }
//
//    public function removeItem(Entity $entity): void
//    {
//        $fileFolderLinker = $this->getEntityManager()->getRepository('FileFolderLinker')
//            ->where(['folderId' => $entity->get('entityId')])
//            ->findOne();
//
//        if (empty($fileFolderLinker)) {
//            return;
//        }
//
//        $fileFolderLinker->set('parentId', '');
//
//        try {
//            $this->getEntityManager()->saveEntity($fileFolderLinker);
//        } catch (UniqueConstraintViolationException $e) {
//            throw new NotUnique($this->getInjection('language')->translate('suchItemNameCannotBeUsedHere', 'exceptions'));
//        }
//    }
//
//    protected function init()
//    {
//        parent::init();
//
//        $this->addDependency('language');
//    }
}