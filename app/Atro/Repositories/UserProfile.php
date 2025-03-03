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

namespace Atro\Repositories;

use Atro\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;

class UserProfile extends User
{
    protected function getEntityById($id)
    {
        $params = [];
        $this->handleSelectParams($params);

        $user = $this->getMapper()->selectById($this->entityFactory->create('User'), $id, $params);

        $entity = $this->entityFactory->create($this->entityType);
        $entity->set($user->toArray());

        return $entity;
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        $user = $this->entityFactory->create('User');
        $user->set($entity->toArray());

        return parent::findRelated($user, $relationName, $params);
    }

    public function countRelated(Entity $entity, $relationName, array $params = [])
    {
        $user = $this->entityFactory->create('User');
        $user->set($entity->toArray());

        return parent::countRelated($user, $relationName, $params);
    }

    protected function insertEntity(Entity $entity, bool $ignoreDuplicate): bool
    {
        throw new Forbidden();
    }

    protected function updateEntity(Entity $entity): bool
    {
        $user = $this->entityFactory->create('User');
        $user->set($entity->toArray());

        return $this->getMapper()->update($user);
    }

    protected function deleteEntity(Entity $entity): bool
    {
        throw new Forbidden();
    }

    public function deleteFromDb(string $id): bool
    {
        throw new Forbidden();
    }
}
