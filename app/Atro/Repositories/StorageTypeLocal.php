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
use Atro\Core\Templates\Repositories\Subtype;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

/**
 * The local storage: its path and the folder structure mode cannot be changed, the path cannot contain the files of
 * another storage.
 */
class StorageTypeLocal extends Subtype
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('syncFolders')) {
                throw new Forbidden();
            }
            if ($entity->isAttributeChanged('path')) {
                throw new BadRequest($this->translate('storagePathCannotBeChanged'));
            }
        }

        $this->validatePath($entity);
    }

    protected function validatePath(Entity $entity): void
    {
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
            throw new BadRequest($this->translate('storagePathNotUnique'));
        }

        $regexp = Converter::isPgSQL($this->getDbal()) ? '~' : 'REGEXP';

        $records = $this->getDbal()->createQueryBuilder()
            ->select("f.id, s.name, l.path, CONCAT(l.path, '/', f.path) as file_path")
            ->from('file', 'f')
            ->innerJoin('f', 'storage', 's', 's.id=f.storage_id')
            ->innerJoin('s', 'storage_type_local', 'l', 'l.supertype_id=s.id')
            ->where('f.deleted=:false')
            ->andWhere('s.deleted=:false')
            ->andWhere('l.id!=:id')
            ->andWhere("CONCAT(l.path, '/', f.path) $regexp :regExp")
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', (string)$entity->get('id'))
            ->setParameter('regExp', "^{$entity->get('path')}")
            ->fetchAllAssociative();

        foreach ($records as $record) {
            if (strlen($record['path']) < strlen($entity->get('path'))) {
                throw new BadRequest($this->translate('storagePathContainFilesFromAnotherStorage'));
            }
        }
    }

    protected function translate(string $key): string
    {
        return $this->getLanguage()->translate($key, 'exceptions', 'Storage');
    }
}
