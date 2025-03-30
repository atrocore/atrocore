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

        $entity->setAsFetched();

        return $entity;
    }

    public function find(array $params = [])
    {
        throw new Forbidden();
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        return parent::findRelated($this->user($entity), $relationName, $params);
    }

    public function countRelated(Entity $entity, $relationName, array $params = [])
    {
        return parent::countRelated($this->user($entity), $relationName, $params);
    }

    public function relate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        return parent::relate($this->user($entity), $relationName, $foreign, $data, $options);
    }

    public function unrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        return parent::unrelate($this->user($entity), $relationName, $foreign, $options);
    }

    protected function insertEntity(Entity $entity, bool $ignoreDuplicate): bool
    {
        throw new Forbidden();
    }

    protected function updateEntity(Entity $entity): bool
    {
        return $this->getMapper()->update($this->user($entity));
    }

    protected function deleteEntity(Entity $entity): bool
    {
        throw new Forbidden();
    }

    public function deleteFromDb(string $id): bool
    {
        throw new Forbidden();
    }

    private function user(Entity $entity): Entity
    {
        $user = $this->entityFactory->create('User');
        $user->set($entity->toArray());

        return $user;
    }
}
