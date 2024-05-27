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

    public function save(Entity $entity, array $options = [])
    {
        $this->getEntityManager()->getPDO()->beginTransaction();

        try {
            $res = parent::save($entity, $options);
            if ($res) {
                $this->updateItem($entity);
            }
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        $this->getEntityManager()->getPDO()->commit();

        return $res;
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->removeItem($entity);

        parent::afterRemove($entity, $options);
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