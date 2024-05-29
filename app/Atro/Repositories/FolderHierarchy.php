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
use Atro\Core\Templates\Repositories\Relation;
use Espo\ORM\Entity;

class FolderHierarchy extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $parentStorage = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('parentId'));
        $entityStorage = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('entityId'));

        if ($parentStorage->get('id') !== $entityStorage->get('id')) {
            throw new BadRequest($this->getInjection('language')->translate('itemCannotBeMovedToAnotherStorage', 'exceptions', 'Storage'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (empty($options['ignoreValidation'])) {
            $parentStorage = $this->getEntityManager()->getRepository('Folder')->getRootStorage();
            $entityStorage = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($entity->get('entityId'));

            if ($parentStorage->get('id') !== $entityStorage->get('id')) {
                throw new BadRequest($this->getInjection('language')->translate('itemCannotBeMovedToAnotherStorage', 'exceptions', 'Storage'));
            }
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}