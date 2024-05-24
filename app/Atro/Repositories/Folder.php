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

use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Hierarchy;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    public static function createFolderHash(?string $name, ?string $parentId): string
    {
        return md5("{$name}_{$parentId}");
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->isAttributeChanged('name')) {
            $entity->set('hash', self::createFolderHash($entity->get('name'), $entity->getParentId()));
        }

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (NotUnique $e) {
            throw new NotUnique($this->getInjection('language')->translate('suchFolderNameCannotBeUsed', 'exceptions', 'Folder'));
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
