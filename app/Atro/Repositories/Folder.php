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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    public function getFolderStorage(string $folderId): StorageEntity
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

            $parent = $folder->getParent();
            if (empty($parent)) {
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

        if ($entity->isNew()) {
            $this->createItem($entity);
        } elseif ($entity->isAttributeChanged('name')) {
            $this->updateItem($entity);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->removeItem($entity);

        parent::afterRemove($entity, $options);
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
