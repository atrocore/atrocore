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

            $file = $this->getEntityManager()->getRepository('File')
                ->where(['storageId' => $entity->get('id')])
                ->findOne();

            if (!empty($file)) {
                throw new BadRequest($this->translate('storageHasFiles', 'exceptions', 'Storage'));
            }

            $folder = $this->getEntityManager()->getRepository('Folder')
                ->where(['storageId' => $entity->get('id')])
                ->findOne();

            if (!empty($folder)) {
                throw new BadRequest($this->translate('storageHasFolders', 'exceptions', 'Storage'));
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

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $e = $this->getEntityManager()->getRepository('File')
            ->select(['id'])
            ->where([
                'storageId' => $entity->get('id')
            ])
            ->findOne();

        if (!empty($e)) {
            throw new BadRequest($this->translate('storageWithFilesCannotBeRemoved', 'exceptions', 'Storage'));
        }

        parent::beforeRemove($entity, $options);
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
