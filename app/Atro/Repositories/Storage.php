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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Storage extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->get('folderId') === null) {
            $entity->set('folderId', '');
        }

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('syncFolders')) {
                throw new Forbidden();
            }
            if ($entity->isAttributeChanged('type')) {
                throw new BadRequest($this->translate('storageTypeCannotBeChanged', 'exceptions', 'Storage'));
            }
            if ($entity->get('type') === 'local' && $entity->isAttributeChanged('path')) {
                throw new BadRequest($this->translate('storagePathCannotBeChanged', 'exceptions', 'Storage'));
            }
        }

        $this->validateLocalPath($entity);

        if ($entity->isAttributeChanged('folderId')) {
            $file = $this->getEntityManager()->getRepository('File')
                ->where(['folderId' => $entity->get('folderId')])
                ->findOne();

            if (!empty($file)) {
                throw new BadRequest($this->translate('folderInUse', 'exceptions', 'Storage'));
            }

            $folderHierarchy = $this->getEntityManager()->getRepository('FolderHierarchy')
                ->where(['parentId' => $entity->get('folderId')])
                ->findOne();

            if (!empty($folderHierarchy)) {
                throw new BadRequest($this->translate('folderInUse', 'exceptions', 'Storage'));
            }

            $file = $this->getEntityManager()->getRepository('File')
                ->where(['storageId' => $entity->get('id')])
                ->findOne();

            if (!empty($file)) {
                throw new BadRequest($this->translate('storageHasItems', 'exceptions', 'Storage'));
            }

            $folder = $this->getEntityManager()->getRepository('Folder')
                ->where(['storageId' => $entity->get('id')])
                ->findOne();

            if (!empty($folder)) {
                throw new BadRequest($this->translate('storageHasItems', 'exceptions', 'Storage'));
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        // relate all children folders with storage
        $children = $this->getEntityManager()->getRepository('Folder')->getChildrenArray($entity->get('folderId'));
        foreach (array_merge([$entity->get('folderId')], array_column($children, 'id')) as $folderId) {
            $this->getConnection()->createQueryBuilder()
                ->update('folder')
                ->set('storage_id', ':storageId')
                ->where('id=:id')
                ->setParameter('storageId', $entity->get('id'))
                ->setParameter('id', $folderId)
                ->executeQuery();
        }
    }

    protected function validateLocalPath(Entity $entity): void
    {
        if ($entity->get('type') !== 'local') {
            return;
        }

        if (empty($entity->get('path'))) {
            throw new BadRequest("Path cannot be empty.");
        }

        $existed = $this
            ->where([
                'path' => $entity->get('path'),
                'id!=' => $entity->get('id')
            ])
            ->findOne();
        if (!empty($existed)) {
            throw new BadRequest($this->translate('storagePathNotUnique', 'exceptions', 'Storage'));
        }

        $regexp = Converter::isPgSQL($this->getConnection()) ? '~' : 'REGEXP';

        $records = $this->getConnection()->createQueryBuilder()
            ->select("f.id, s.name, s.path, CONCAT(s.path, '/', f.path) as file_path")
            ->from('file', 'f')
            ->innerJoin('f', 'storage', 's', 's.id=f.storage_id')
            ->where('f.deleted=:false')
            ->andWhere('s.deleted=:false')
            ->andWhere('s.type=:local')
            ->andWhere('s.id!=:id')
            ->andWhere("CONCAT(s.path, '/', f.path) $regexp :regExp")
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('local', 'local')
            ->setParameter('id', $entity->get('id'))
            ->setParameter('regExp', "^{$entity->get('path')}")
            ->fetchAllAssociative();

        foreach ($records as $record) {
            if (strlen($record['path']) < strlen($entity->get('path'))) {
                throw new BadRequest($this->translate('storagePathContainFilesFromAnotherStorage', 'exceptions', 'Storage'));
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->unlinkAllFolders($entity->get('id'));
        $this->unlinkAllFiles($entity->get('id'));
    }

    public function unlinkAllFolders(string $storageId): void
    {
        while (true) {
            $foldersIds = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('folder')
                ->where('storage_id=:storageId')
                ->setFirstResult(0)
                ->setMaxResults(20000)
                ->setParameter('storageId', $storageId)
                ->fetchFirstColumn();

            if (empty($foldersIds)) {
                break;
            }

            $this->getConnection()->createQueryBuilder()
                ->delete('file_folder_linker')
                ->where('folder_id IN (:foldersIds)')
                ->setParameter('foldersIds', $foldersIds, $this->getConnection()::PARAM_STR_ARRAY)
                ->executeQuery();

            $this->getConnection()->createQueryBuilder()
                ->delete('folder_hierarchy')
                ->where('entity_id IN (:foldersIds) OR parent_id IN (:foldersIds)')
                ->setParameter('foldersIds', $foldersIds, $this->getConnection()::PARAM_STR_ARRAY)
                ->executeQuery();

            $this->getConnection()->createQueryBuilder()
                ->delete('folder')
                ->where('id IN (:foldersIds)')
                ->setParameter('foldersIds', $foldersIds, $this->getConnection()::PARAM_STR_ARRAY)
                ->executeQuery();
        }
    }

    public function unlinkAllFiles(string $storageId): void
    {
        while (true) {
            $filesIds = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('file')
                ->where('storage_id=:storageId')
                ->setFirstResult(0)
                ->setMaxResults(20000)
                ->setParameter('storageId', $storageId)
                ->fetchFirstColumn();

            if (empty($filesIds)) {
                break;
            }

            $this->getConnection()->createQueryBuilder()
                ->delete('file_folder_linker')
                ->where('file_id IN (:filesIds)')
                ->setParameter('filesIds', $filesIds, $this->getConnection()::PARAM_STR_ARRAY)
                ->executeQuery();

            $this->getConnection()->createQueryBuilder()
                ->delete('file')
                ->where('id IN (:filesIds)')
                ->setParameter('filesIds', $filesIds, $this->getConnection()::PARAM_STR_ARRAY)
                ->executeQuery();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function translate(string $key, string $category, string $scope): string
    {
        return $this->getInjection('language')->translate($key, $category, $scope);
    }
}
