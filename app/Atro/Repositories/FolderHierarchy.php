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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;

class FolderHierarchy extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
//        $parentFolder = $this->getEntityManager()->getRepository('Folder')->get($entity->get('parentId'));
//        if (empty($parentFolder)){
//            $parentStorageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage('', true)->get('id');
//        }else{
//            $parentStorageId = $parentFolder->get('storageId');
//        }
//
//        $currentFolder = $this->getEntityManager()->getRepository('Folder')->get($entity->get('entityId'));
//
//        if ($parentStorageId !== $currentFolder->get('storageId')) {
//            throw new BadRequest($this->getInjection('language')->translate('fileCannotBeMovedToAnotherStorage', 'exceptions', 'File'));
//        }

        parent::beforeSave($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}