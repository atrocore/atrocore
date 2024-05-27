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
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $folderStorageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('entityId') ?? '', true)->get('id');
        $parentFolderStorageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('parentId') ?? '', true)->get('id');

        if ($folderStorageId !== $parentFolderStorageId) {
            throw new BadRequest($this->getInjection('language')->translate('fileCannotBeMovedToAnotherStorage', 'exceptions', 'File'));
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

    protected function updateEntity(Entity $entity): bool
    {
        $inTransaction = $this->getPDO()->inTransaction();

        if (!$inTransaction) {
            $this->getPDO()->beginTransaction();
        }

        try {
            $res = parent::updateEntity($entity);
            if ($res) {
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

    public function updateItem(Entity $entity): void
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->update('file_folder_linker')
            ->set('parent_id', ':parentId')
            ->where('folder_id=:folderId')
            ->setParameter('parentId', (string)$entity->get('parentId'))
            ->setParameter('folderId', (string)$entity->get('entityId'));
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
            ->setParameter('folderId', $entity->get('entityId'))
            ->executeQuery();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}