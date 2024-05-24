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

use Doctrine\DBAL\ParameterType;
use Espo\Core\Templates\Repositories\Hierarchy;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->isAttributeChanged('name')) {
            $entity->set('hash', $this->prepareFolderHash($entity->get('id'), $entity->get('name')));
        }

        parent::beforeSave($entity, $options);
    }

    public function prepareFolderHash(string $folderId, string $folderName = null): string
    {
        $record = $this->getConnection()->createQueryBuilder()
            ->select('f.*, h.parent_id')
            ->from('folder', 'f')
            ->leftJoin('f', 'folder_hierarchy', 'h', 'f.id=h.entity_id')
            ->where('f.deleted=:false')
            ->andWhere('f.deleted=:false')
            ->andWhere('f.id=:id')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $folderId)
            ->fetchAssociative();

        if (!$folderName) {
            $folderName = $record['name'];
        }

        return md5("{$folderName}_{$record['parent_id']}");
    }
}
